<?php namespace App\Command;

use App\Accountant;
use App\Entity\Account;
use App\Entity\Payout;
use App\Entity\PayoutMethod;
use App\Entity\Transaction;
use App\Entity\User;
use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientFundsException;
use App\FeeFetcher;
use App\MessageBroker;
use App\PaymentAccountFetcher;
use App\PaymentSystemManager\PayoutInterface;
use App\PaymentSystemManager\QiwiManager;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Ewll\DBBundle\DB\Client as DbClient;
use Ewll\DBBundle\Exception\ExecuteException;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestLockCommand extends Command
{
    const COMMAND_NAME = 'test-lock';

    private $defaultDbClient;
    private $paymentAccountFetcher;
    private $repositoryProvider;
    private $messageBroker;
    private $feeFetcher;
    private $logger;
    private $qiwiManager;
    private $guzzleClient;

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);
    }

    public function __construct(
        DbClient $defaultDbClient,
        PaymentAccountFetcher $paymentAccountFetcher,
        RepositoryProvider $repositoryProvider,
        MessageBroker $messageBroker,
        FeeFetcher $feeFetcher,
        Logger $logger,
        QiwiManager $qiwiManager,
        GuzzleClient $guzzleClient
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->defaultDbClient = $defaultDbClient;
        $this->paymentAccountFetcher = $paymentAccountFetcher;
        $this->repositoryProvider = $repositoryProvider;
        $this->messageBroker = $messageBroker;
        $this->feeFetcher = $feeFetcher;
        $this->logger = $logger;
        $this->qiwiManager = $qiwiManager;
        $this->guzzleClient = $guzzleClient;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        echo '1';
        echo $this->guzzleClient->post(
            'http://127.0.0.1:8332',
            [
                'body' => '{"jsonrpc": "1.0", "id":"curltest", "method": "getwalletinfo", "params": [] }',
                'auth' => ['admin', 'admin']
            ]
        )->getBody();
        echo '2';
        /*$paymentAccount = $this->paymentAccountFetcher->fetchOne(3, PaymentAccountFetcher::ENABLED_FOR_MERCHANT, 3);
        $a = '';*/
        /*for ($i=0; $i<5; $i++) {
            $output->writeln("Attempt #$i");
            try {
                $this->defaultDbClient->beginTransaction();

                $this->defaultDbClient->prepare('SELECT * FROM testLock WHERE test = 5 and test1 = 6 FOR UPDATE')->execute();
                $output->writeln('Select For Update done, sleeping...');
                sleep(5);

                $this->defaultDbClient->prepare('UPDATE testLock SET info = '.rand().' WHERE id = 1')->execute();

                $this->defaultDbClient->commit();
                $output->writeln('Commit done');
                break;
            } catch (Exception $e) {
                $isExecuteException = $e instanceof ExecuteException;
                $output->writeln("Exception. ExecuteException - $isExecuteException, code {$e->getCode()}");
                $this->defaultDbClient->rollback();
                if ($isExecuteException && $e->getCode() === ExecuteException::DEADLOCK_CODE) {
                    $output->writeln('Deadlock');
                    continue;
                }
                throw $e;
            }
        }
        $output->writeln('End');*/
exit();
        $payoutMethodQiwi = $this->repositoryProvider->get(PayoutMethod::class)->findById(2);
        $user = $this->repositoryProvider->get(User::class)->findById(4);
        $internalUsersId = rand (1, 100);

        $this->payout(
            $payoutMethodQiwi,
            $user,
            $internalUsersId,
            $this->qiwiManager,
            '5',
            3,
            '+79206973502'
        );
    }

//    /**
//     * @throws InsufficientFundsException
//     * @throws AccountNotFoundException
//     */
//    public function payout(
//        PayoutMethod $payoutMethod,
//        User $user,
//        int $internalUsersId,
//        PayoutInterface $paymentSystemManager,
//        string $amount,
//        int $currencyId,
//        string $receiver
//    ): int {
//        /** @var AccountRepository $accountRepository */
//        $accountRepository = $this->repositoryProvider->get(Account::class);
//        /** @var TransactionRepository $transactionRepository */
//        $transactionRepository = $this->repositoryProvider->get(Transaction::class);
//
//        /*DEBUG*/$debugLogData = ['internalUsersId' => $internalUsersId];
//        for ($i=0; $i<5; $i++) {
//            try {
//                $this->defaultDbClient->beginTransaction();
//                /*DEBUG*/$this->logger->info('TEST.Transaction started', $debugLogData);
//
//                $account = $accountRepository->findAccountByUserIdCurrencyIdWithLock($user->id, $currencyId);
//                /*DEBUG*/$this->logger->info('TEST.Sleep', $debugLogData);
//                sleep(5);
//                if (null === $account) {
//                    throw new AccountNotFoundException();
//                }
//
//                $fee = $this->feeFetcher->fetchPayoutFee($payoutMethod, $user);
//                $feeAmount = $this->feeFetcher->calcFeeAmount($amount, $fee);
//                $credit = bcadd($amount, $feeAmount, 2);
//                $payout = Payout::create(
//                    $payoutMethod->id,
//                    $user->id,
//                    $internalUsersId,
//                    $account->id,
//                    $paymentSystemManager->getPaymentSystemId(),
//                    $receiver,
//                    $amount,
//                    $feeAmount,
//                    $credit
//                );
//
//                $this->repositoryProvider->get(Payout::class)->create($payout);
//                /*DEBUG*/$debugLogData['payoutId'] = $payout->id;
//                /*DEBUG*/$this->logger->info('TEST.Payout created', $debugLogData);
//                $this->messageBroker->createMessage(
//                    $paymentSystemManager->getPayoutQueueName(),
//                    ['id' => $payout->id, 'try' => 1,],
//                    15
//                );
//                $methodId = $payout->id;
//
//                $unexecutedDecreaseTransactionSum = $transactionRepository
//                    ->calcUnexecutedDecreaseTransactionSum($user->id, $currencyId);
//                $balance = $transactionRepository->getBalance($user->id, $currencyId);
//
//                $realBalance = bcadd($balance, $unexecutedDecreaseTransactionSum, 2);
//                $isEnough = bccomp($realBalance, $credit, 2) > -1;
//                if (!$isEnough) {
//                    throw new InsufficientFundsException();
//                }
//
//                $transactionAmount = bcmul($credit, '-1', 2);
//                $transaction = Transaction::create(
//                    $user->id,
//                    Accountant::METHOD_PAYOUT,
//                    $methodId,
//                    $transactionAmount,
//                    $currencyId
//                );
//                $transactionRepository->create($transaction);
//                $this->messageBroker->createMessage(
//                    MessageBroker::QUEUE_TRANSACTION_NAME,
//                    ['id' => $transaction->id],
//                    15
//                );
//
//                $this->defaultDbClient->commit();
//                /*DEBUG*/$this->logger->info('TEST.Commited', $debugLogData);
//
//                return $payout->id;
//            } catch (Exception|InsufficientFundsException|AccountNotFoundException $e) {
//                /*DEBUG*/$debugLogData['error'] = $e->getMessage();
//                /*DEBUG*/$this->logger->error('TEST.Exception', $debugLogData);
//                $this->defaultDbClient->rollback();
//                if ($e instanceof ExecuteException && $e->getCode() === ExecuteException::DEADLOCK_CODE) {
//                    continue;
//                }
//                throw $e;
//            }
//        }
//        throw new RuntimeException('Cannot realise locking');
//    }
}
