<?php namespace App\Daemon;

use App\Entity\PaymentAccount;
use App\Entity\PaymentSystem;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\MessageBroker;
use App\PaymentAccountFetcher;
use App\PaymentSystemManager\PaymentSystemManagerInterface;
use App\PaymentSystemManager\PayoutWithChecking;
use App\PaymentSystemManager\WhiteBalancingInterface;
use App\TagServiceProvider\TagServiceProvider;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

class WhiteBalancingDaemon extends Daemon
{
    private $messageBroker;
    private $tagServiceProvider;
    private $paymentSystemManagers;
    private $paymentAccountFetcher;
    private $isWhiteBalancingEnabled;

    protected function configure()
    {
        $this->setName('daemon:white-balancing');
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        TagServiceProvider $tagServiceProvider,
        iterable $paymentSystemManagers,
        PaymentAccountFetcher $paymentAccountFetcher,
        string $isWhiteBalancingEnabled
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->tagServiceProvider = $tagServiceProvider;
        $this->paymentSystemManagers = $paymentSystemManagers;
        $this->paymentAccountFetcher = $paymentAccountFetcher;
        $this->isWhiteBalancingEnabled = $isWhiteBalancingEnabled;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_WHITE_BALANCING_NAME);
        $this->logExtraDataKeeper->setParam('paymentAccountId', $message['id']);
        if (!$this->isWhiteBalancingEnabled) {
            $this->logger->info('White balancing is disabled');

            return;
        }

        $paymentAccountRepository = $this->repositoryProvider->get(PaymentAccount::class);
        /** @var PaymentAccount $paymentAccount */
        $paymentAccount = $paymentAccountRepository->findById($message['id']);
        if (!$paymentAccount->isActive) {
            $this->logger->error("Account is not active");

            return;
        }
        /** @var PaymentSystem $paymentSystem */
        $paymentSystem = $this->repositoryProvider->get(PaymentSystem::class)
            ->findById($paymentAccount->paymentSystemId);
        /** @var PaymentSystemManagerInterface $paymentSystemManager */
        $paymentSystemManager = $this->tagServiceProvider
            ->get($this->paymentSystemManagers, $paymentSystem->name);
        if (null === $paymentSystemManager || !$paymentSystemManager instanceof WhiteBalancingInterface) {
            $this->logger->error('Payment system manager not found');

            return;
        }

        $balanceKey = $paymentSystemManager->getPayoutBalanceKey();
        $balance = $paymentAccount->getBalance($balanceKey);

        if (!$paymentSystemManager->isBalanceOverBalancingPoint($paymentAccount)) {
            $this->logger->error("Balance is not over balancing point: {$balance}");

            return;
        }

        $whitePaymentAccount = $this->paymentAccountFetcher->fetchOneForWhiteBalancing($paymentSystem->id);
        if (null === $whitePaymentAccount) {
            $this->logger->info('White account not found', ['paymentSystemId' => $paymentSystem->id]);

            return;
        }

        $whiteBalance = $whitePaymentAccount->getBalance($balanceKey);
        $amount = bcsub($balance, WhiteBalancingInterface::BALANCING_KEEP_AMOUNT, 2);
        $receiver = $paymentSystemManager->getAccountReceiver($whitePaymentAccount);

        try {
            $initData = $paymentSystemManager->transfer($paymentAccount, $amount, $receiver, 'B-Transfer');

            $this->logger->info('Transfer', [
                'paymentAccountId' => $paymentAccount->id,
                'balance' => $balance,
                'whitePaymentAccountId' => $whitePaymentAccount->id,
                'amount' => $amount,
                'receiver' => $receiver,
            ]);

            // Crutch!!!
            if ($paymentSystemManager instanceof PayoutWithChecking) {
                $this->logger
                    ->info('This paymentSystemManager with PayoutWithChecking. Waiting 10 second before checking');
                sleep(10);
                $checkResult = $paymentSystemManager->checkPayout($initData, $paymentAccount);
                $this->logger->info("Done. checkResult: '$checkResult'");
            }

            $balance = bcsub($balance, $amount, 2);
            $paymentAccount->setBalanceItem($balance, $balanceKey);
            $paymentAccountRepository->update($paymentAccount, ['balance']);

            $whiteBalance = bcadd($whiteBalance, $amount, 2);
            $whitePaymentAccount->setBalanceItem($whiteBalance, $balanceKey);
            $paymentAccountRepository->update($whitePaymentAccount, ['balance']);
        } catch (CannotTransferTimeoutException $e) {
            $this->logger->error('Cannot transfer, timeout', ['message' => $e->getMessage()]);
        } catch (CannotTransferException $e) {
            $this->logger->error('Cannot transfer', ['message' => $e->getMessage()]);
        }
    }
}
