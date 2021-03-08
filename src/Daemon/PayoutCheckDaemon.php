<?php namespace App\Daemon;

use App\Entity\PaymentAccount;
use App\Entity\PaymentSystem;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\MessageBroker;
use App\PaymentAccountFetcher;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\PaymentSystemManager\PayoutWithChecking;
use App\Payout\Processor\Exception\ProcessCheckingOrUnknownStatusException;
use App\Payout\Processor\PayoutProcessor;
use App\TagServiceProvider\TagServiceProvider;
use App\TelegramSender;
use Ewll\DBBundle\DB\Client as DbClient;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PayoutCheckDaemon extends Daemon
{
    private const CHECKING_INTERVAL_SEC = 1200;
    private const CHECKING_PAUSE_SEC = 30;

    private $messageBroker;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $paymentAccountFetcher;
    private $defaultDbClient;
    private $telegramSender;
    private $payoutProcessor;

    protected function configure()
    {
        $this
            ->setName('daemon:payout-check');
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        PaymentAccountFetcher $paymentAccountFetcher,
        DbClient $defaultDbClient,
        TelegramSender $telegramSender,
        PayoutProcessor $payoutProcessor
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->paymentAccountFetcher = $paymentAccountFetcher;
        $this->defaultDbClient = $defaultDbClient;
        $this->telegramSender = $telegramSender;
        $this->payoutProcessor = $payoutProcessor;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_PAYOUT_CHECK);
        $this->logExtraDataKeeper->setParam('payoutId', $message['id']);
        /** @var Payout $payout */
        $payout = $this->repositoryProvider->get(Payout::class)->findById($message['id']);
        if (null === $payout) {
            $this->logger->error('Not found');

            return;
        }
        /** @var PayoutSet $payoutSet */
        $payoutSet = $this->repositoryProvider->get(PayoutSet::class)->findById($payout->payoutSetId);
        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($payoutSet->paymentSystemId);
        /** @var PaymentSystemManagerInterface|PayoutInterface|PayoutWithChecking $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        $isPayoutManager = $paymentSystemManager instanceof PayoutInterface;
        $isPayoutWithCheckingManager = $paymentSystemManager instanceof PayoutWithChecking;
        if (null === $paymentSystemManager || !$isPayoutManager || !$isPayoutWithCheckingManager) {
            $this->logger->error('Payment system manager not found');

            return;
        }

        if ($payout->statusId !== Payout::STATUS_ID_CHECKING) {
            $this->logger->error("Status: {$payout->statusId}. Expect: " . Payout::STATUS_ID_CHECKING);

            return;
        }

        $this->logger->info('Checking');
        try {
            $payoutCheckingResult = $this->payoutProcessor->processCheckingOrUnknownStatus($payout);
            $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)
                ->findById($payout->paymentAccountId);
            $timeWaiting = time() - $message['createdTs'];
            if ($payoutCheckingResult === 0 && $timeWaiting > self::CHECKING_INTERVAL_SEC) {
                $this->payoutProcessor->moveToUnknownStatus($payout, $payoutSet, $paymentAccount, '???');
            } elseif ($payoutCheckingResult === 0) {
                $this->returnToQueue(MessageBroker::QUEUE_PAYOUT_CHECK, $message, self::CHECKING_PAUSE_SEC);
            }
        } catch (ProcessCheckingOrUnknownStatusException $e) {
            switch ($e->getCode()) {
                case ProcessCheckingOrUnknownStatusException::CODE_CANNOT_CHECK:
                    $this->logger->error('CannotCheckException', [$e->getMessage()]);

                    throw $e;
                case ProcessCheckingOrUnknownStatusException::CODE_NOT_ENOUGH_FUNDS:
                    $this->logger->error('NotEnoughFundsException, full retry', [$e->getMessage()]);
                    $this->fullRetry($payout, $paymentSystemManager, $message);

                    break;
                default:
                    throw new RuntimeException('Unhandled error code of ProcessCheckingOrUnknownStatusException');
            }
        }
    }

    private function fullRetry(Payout $payout, PayoutInterface $paymentSystemManager, array $message)
    {
        $message['try'] = 1;
        $this->payoutProcessor
            ->updateStatus($payout, Payout::STATUS_ID_QUEUE, 'error', 'Not enough funds, full retry');
        $this->returnToQueue($paymentSystemManager->getPayoutQueueName(), $message, 15);
    }

    private function returnToQueue(string $queueName, array $message, int $delay): void
    {
        $this->logger->info('Return to queue');
        $this->messageBroker->createMessage(
            $queueName,
            $message,
            $delay
        );
    }
}
