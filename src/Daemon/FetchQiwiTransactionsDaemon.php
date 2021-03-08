<?php namespace App\Daemon;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\PaymentSystem;
use App\Exception\PaymentManagerCheckingException;
use App\Exception\PaymentShotNotExpectedToExecException;
use App\Exception\PaymentShotNotFoundException;
use App\MessageBroker;
use App\PaymentAccountant;
use App\PaymentSystemManager\QiwiManager;
use App\Repository\PaymentShotRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FetchQiwiTransactionsDaemon extends Daemon
{
    const TRANSACTION_LIFETIME_MINUTES = 30;
    const MAX_TRANSACTION_NUM_PER_LIFETIME = 125;
    const FETCHING_PORTION_SIZE = 25;
    const MAX_REQUEST_TRIES_NUM_TO_DEACTIVATE_ACCOUNT = 20;

    private $guzzleClient;
    private $qiwiManager;
    private $messageBroker;
    private $paymentAccountant;
    private $requestErrorsByAccountCounter = [];
    private $notIdentifiedTransactionCounter = [];

    protected function configure()
    {
        $this->setName('daemon:qiwi-transactions-fetch');
    }

    public function __construct(
        Logger $logger,
        Client $guzzleClient,
        QiwiManager $qiwiManager,
        MessageBroker $messageBroker,
        PaymentAccountant $paymentAccountant
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->guzzleClient = $guzzleClient;
        $this->qiwiManager = $qiwiManager;
        $this->messageBroker = $messageBroker;
        $this->paymentAccountant = $paymentAccountant;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        /** @var PaymentAccount[] $paymentAccounts */
        $paymentAccounts = $this->repositoryProvider->get(PaymentAccount::class)
            ->findBy(['paymentSystemId' => PaymentSystem::QIWI_ID, 'isActive' => 1], 'id');

        /** @var PaymentShotRepository $paymentShotRepository */
        $paymentShotRepository = $this->repositoryProvider->get(PaymentShot::class);
        $paymentShots = $paymentShotRepository
            ->getLastShotsByPaymentMethodId(self::TRANSACTION_LIFETIME_MINUTES, 0, PaymentMethod::METHOD_QIWI_ID);

        $paymentShotsByPaymentAccountsCounter = [];
        foreach ($paymentShots as $paymentShot) {
            if (!isset($paymentAccounts[$paymentShot->paymentAccountId])) {
                continue;
            }
            if (!isset($paymentShotsByPaymentAccountsCounter[$paymentShot->paymentAccountId])) {
                $paymentShotsByPaymentAccountsCounter[$paymentShot->paymentAccountId] = [
                    'num' => 0,
                    'unhandledNum' => 0,
                ];
            }
            $paymentShotsByPaymentAccountsCounter[$paymentShot->paymentAccountId]['num']++;
            if ($paymentShot->statusId === PaymentShot::STATUS_ID_WAITING) {
                $paymentShotsByPaymentAccountsCounter[$paymentShot->paymentAccountId]['unhandledNum']++;
            }
        }

        //TODO перемешать аккаунты
        foreach ($paymentShotsByPaymentAccountsCounter as $paymentAccountId => $paymentShotsCounter) {
            if ($paymentShotsCounter['unhandledNum'] > 0) {
                if ($paymentShotsCounter['num'] > self::MAX_TRANSACTION_NUM_PER_LIFETIME) {
                    //TODO crit trigger
                    $this->logger->critical(
                        "Excess of transaction num on account #$paymentAccountId",
                        ['num' => $paymentShotsCounter['num'], 'lifetime' => self::MAX_TRANSACTION_NUM_PER_LIFETIME]
                    );
                }
                $paymentAccount = $paymentAccounts[$paymentAccountId];
                try {
                    if (0 !== $paymentShotsCounter['num']) {
                        $transactions = $this->fetchQiwiTransactions($paymentAccount, $paymentShotsCounter['num']);
                    }
                    $this->requestErrorsByAccountCounter[$paymentAccountId] = 0;
                    foreach ($transactions as $transaction) {
                        $this->handleTransaction($transaction, $paymentShots, $paymentAccount);
                    }
                } catch (RequestException $e) {
                    $this->logger
                        ->error('Request error', ['accountId' => $paymentAccountId, 'error' =>$e->getMessage()]);
                    if (!isset($this->requestErrorsByAccountCounter[$paymentAccountId])) {
                        $this->requestErrorsByAccountCounter[$paymentAccountId] = 0;
                    }
                    ++$this->requestErrorsByAccountCounter[$paymentAccountId];
                    $triesNum = $this->requestErrorsByAccountCounter[$paymentAccountId];
                    if ($triesNum === self::MAX_REQUEST_TRIES_NUM_TO_DEACTIVATE_ACCOUNT) {
                        $this->requestErrorsByAccountCounter[$paymentAccountId] = 0;
                        $this->paymentAccountant->deactivateAccount($paymentAccount);
                    }
                }
            }
        }

        sleep(3);
    }

    private function fetchQiwiTransactions(PaymentAccount $paymentAccount, int $num)
    {
        if (!empty($this->notIdentifiedTransactionCounter[$paymentAccount->id])) {
            $additionalTransactionNum = $this->notIdentifiedTransactionCounter[$paymentAccount->id];
            $num += $additionalTransactionNum;
            $this->logger->info("Additional transaction num '$additionalTransactionNum' for #{$paymentAccount->id}");
        }

        $iterationsNum = (int)ceil($num / self::FETCHING_PORTION_SIZE);

        $transactions = [];
        $nextTxnId = null;
        $nextTxnDate = null;
        for ($i = 1; $i <= $iterationsNum; $i++) {
            $portionSize = $i === $iterationsNum
                ? $num - ($i - 1) * self::FETCHING_PORTION_SIZE
                : self::FETCHING_PORTION_SIZE;
            $result = $this->request($paymentAccount, $portionSize, $nextTxnId, $nextTxnDate);
            $nextTxnId = $result['nextTxnId'];
            $nextTxnDate = $result['nextTxnDate'];
            $transactions = array_merge($transactions, $result['data']);
            if (empty($nextTxnId)) {
                break;
            }
        }

        $this->logger->info('Total got rows num: ' . count($transactions) . ' for #' . $paymentAccount->id);

        $this->notIdentifiedTransactionCounter[$paymentAccount->id] = 0;

        return $transactions;
    }

    private function request(PaymentAccount $paymentAccount, $portionSize, $nextTxnId = null, $nextTxnDate = null)
    {
        $params = [
            'rows' => $portionSize,
            'operation' => 'IN'
        ];
        if (null !== $nextTxnId) {
            $params['nextTxnId'] = $nextTxnId;
            $params['nextTxnDate'] = $nextTxnDate;
        }
        $this->logger->debug('Request with params', $params);
        $query = http_build_query($params);
        $request = $this->guzzleClient->request(
            'GET',
            "https://edge.qiwi.com/payment-history/v1/persons/{$paymentAccount->config['account']}/payments?$query",
            [
                'timeout' => 3,
                'connect_timeout' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$paymentAccount->config['token']}",
                ]
            ]
        );
        //TODO notification
        $result = json_decode($request->getBody()->getContents(), true);
        $rowsNumGot = count($result['data']);
        $this->logger->info("Got rows num: $rowsNumGot");

        return $result;
    }

    private function handleTransaction(array $transaction, array $paymentShots, PaymentAccount $paymentAccount)
    {
        $this->logger->debug('Handling transaction', ['txnId' => $transaction['txnId']]);
        if (1 === preg_match(Payment::PAYMENT_ID_REGEXP, $transaction['comment'], $matches)) {
            try {
                $paymentId = (int)$matches[1];
                $this->logger->debug('Transaction identified', ['paymentId' => $paymentId]);
                $paymentShot = $this->getPaymentShotForApply($paymentId, $paymentShots);
                if (null === $paymentShot) {
                    throw new PaymentShotNotFoundException();
                }
                if ($paymentShot->statusId !== PaymentShot::STATUS_ID_WAITING) {
                    throw new PaymentShotNotExpectedToExecException($paymentShot->id);
                }
                $this->qiwiManager->checkTransaction($transaction, $paymentShot);
                if ($transaction['status'] === QiwiManager::QIWI_TRANSACTION_STATUS_SUCCESS) {
                    $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
                        'paymentShotId' => $paymentShot->id
                    ]);
                    $this->logger->info("#$paymentId moved to execution");
                } else {
                    $this->logger->info("Status: {$transaction['status']}. Skip executing");
                }
            } catch (PaymentManagerCheckingException $e) {
                $this->logger->error($e->getMessage(), ['txnId' => $transaction['txnId'], 'paymentId' => $paymentId]);
            } catch (PaymentShotNotFoundException|PaymentShotNotExpectedToExecException $e) {
                $this->logger->debug($e->getMessage(), ['txnId' => $transaction['txnId']]);
            }
        } else {
            $this->logger->error('Transaction not identified', [
                'txnId' => $transaction['txnId'], 'comment' => $transaction['comment']
            ]);
            if (!isset($this->notIdentifiedTransactionCounter[$paymentAccount->id])) {
                $this->notIdentifiedTransactionCounter[$paymentAccount->id] = 0;
            }
            ++$this->notIdentifiedTransactionCounter[$paymentAccount->id];
        }
    }

    private function getPaymentShotForApply(int $paymentId, array $paymentShots): ?PaymentShot
    {
        /** @var PaymentShot[] $paymentShots */
        foreach ($paymentShots as $paymentShot) {
            if ($paymentShot->paymentId === $paymentId) {
                return $paymentShot;
            }
        }

        return null;
    }
}
