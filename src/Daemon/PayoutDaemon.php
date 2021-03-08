<?php namespace App\Daemon;

use App\Entity\PaymentAccount;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotFetchPaymentAccountException;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\InsufficientFundsException;
use App\MessageBroker;
use App\PaymentAccountFetcher;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutInterface;
use App\PaymentSystemManager\PayoutWithChecking;
use App\PaymentSystemManager\SelfManager;
use App\Payout\Processor\PayoutProcessor;
use App\TagServiceProvider\TagServiceProvider;
use App\TelegramSender;
use Ewll\DBBundle\DB\Client as DbClient;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PayoutDaemon extends Daemon
{
    private const DELAY_REFETCH_ACCOUNT_SEC = 60;
    private const DELAY_RETRY_SEC = 300;

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
            ->setName('daemon:payout')
            ->addArgument('paymentSystemKey', InputArgument::REQUIRED);
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
        $paymentSystemKey = $input->getArgument('paymentSystemKey');
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystemKey);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof PayoutInterface) {
            $this->logger->error('Payment system manager not found');

            return;
        }

        $message = $this->messageBroker->getMessage($paymentSystemManager->getPayoutQueueName());
        $this->logExtraDataKeeper->setParam('payoutId', $message['id']);
        /** @var Payout $payout */
        $payout = $this->repositoryProvider->get(Payout::class)->findById($message['id']);
        if (null === $payout) {
            $this->logger->error('Not found');

            return;
        }
        /** @var PayoutSet $payoutSet */
        $payoutSet = $this->repositoryProvider->get(PayoutSet::class)->findById($payout->payoutSetId);

        if ($payout->statusId !== Payout::STATUS_ID_QUEUE) {
            $this->logger->error("Status: {$payout->statusId}. Expect: " . Payout::STATUS_ID_QUEUE);

            return;
        }

        $this->logger->info("Execute payout. Attempt: {$message['try']}. Credit: {$payout->credit}");
        $this->logger->info("DEBUG 2");
        $paymentAccount = $this->getPaymentAccount($paymentSystemManager, $payout);
        $this->logger->info("DEBUG 3");
        $description = 'Payout';
        $this->logger->info("DEBUG 5");
        $this->payoutProcessor->updateStatus($payout, Payout::STATUS_ID_PROCESS);
        $this->logger->info("DEBUG 10");
        try {
            $paymentSystemManager->payout($payout, $payoutSet, $description, $paymentAccount);
            $this->logger->info("DEBUG 15");
        } catch (CannotTransferTimeoutException $e) {
            $this->payoutProcessor->moveToUnknownStatus($payout, $payoutSet, $paymentAccount, $e->getMessage());

            return;
        } catch (CannotTransferException $e) {
            $context = ['message' => $e->getMessage()];
            if ($message['try'] < 3) {
                $this->messageBroker->createMessage($paymentSystemManager->getPayoutQueueName(), [
                    'id' => $payout->id,
                    'try' => $message['try'] + 1,
                ], self::DELAY_RETRY_SEC);
                $this->payoutProcessor
                    ->updateStatus($payout, Payout::STATUS_ID_QUEUE, 'info', 'Cannot payout. Retry', $context);
            } else {
                $this->payoutProcessor->moveToFailStatusWithRefund($payout, $payoutSet, $context);
            }

            return;
        }

        if (null !== $paymentAccount) {
            $balanceKey = $paymentSystemManager->getPayoutBalanceKey();
            $payout->paymentAccountId = $paymentAccount->id;
            //@TODO списывается сумма с учетом нашей комиссии, а не комиссии системы.
            //@TODO В большинстве случаев настоящий баланс будет чуть больше
            $newAccountBalance = bcsub($paymentAccount->getBalance($balanceKey), $payout->amount, 2);
            $paymentAccount->setBalanceItem($newAccountBalance, $balanceKey);
            $this->repositoryProvider->get(PaymentAccount::class)->update($paymentAccount, ['balance']);
        }

        if ($paymentSystemManager instanceof PayoutWithChecking) {
            $this->putToQueueForChecking($payout);
            $this->payoutProcessor->updateStatus($payout, Payout::STATUS_ID_CHECKING, 'info', 'To checking');
        } else {
            $this->payoutProcessor->moveToSuccessStatus($payout, $payoutSet);
        }
    }

    private function putToQueueForChecking(Payout $payout)
    {
        $this->messageBroker->createMessage(
            MessageBroker::QUEUE_PAYOUT_CHECK,
            ['id' => $payout->id, 'createdTs' => time(), 'isChecking' => true],
            15
        );
    }

    private function getPaymentAccount(
        PayoutInterface $paymentSystemManager,
        Payout $payout
    ): ?PaymentAccount {
        if ($paymentSystemManager instanceof SelfManager) {
            return null;
        }
        while (1) {
            try {
                $balanceKey = $paymentSystemManager->getPayoutBalanceKey();
                $paymentAccount = $this->paymentAccountFetcher->fetchOneForPayout(
                    $paymentSystemManager->getPaymentSystemId(),
                    $balanceKey,
                    $payout->credit,
                    $this->logger
                );

                return $paymentAccount;
            } catch (CannotFetchPaymentAccountException|InsufficientFundsException $e) {
                $this->logger->error("{$e->getMessage()}  Waiting " . self::DELAY_REFETCH_ACCOUNT_SEC . ' sec');
            }
            sleep(self::DELAY_REFETCH_ACCOUNT_SEC);
        }
    }
}
