<?php namespace App\Command;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\PaymentSystem;
use App\Exception\PaymentManagerCheckingException;
use App\Exception\PaymentShotNotExpectedToExecException;
use App\Exception\PaymentShotNotFoundException;
use App\MessageBroker;
use App\PaymentSystemManager\QiwiManager;
use DateTime;
use GuzzleHttp\Client as GuzzleClient;
use Monolog\Logger;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QiwiReexec extends AbstractCommand
{
    const COMMAND_NAME = 'qiwi:reexec';

    private $guzzleClient;
    private $qiwiManager;
    private $messageBroker;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('paymentAccountId', InputArgument::REQUIRED)
            ->addArgument('interval', InputArgument::REQUIRED);
    }

    public function __construct(
        Logger $logger,
        GuzzleClient $guzzleClient,
        QiwiManager $qiwiManager,
        MessageBroker $messageBroker
    ) {
        parent::__construct(self::COMMAND_NAME);
        $this->guzzleClient = $guzzleClient;
        $this->logger = $logger;
        $this->qiwiManager = $qiwiManager;
        $this->messageBroker = $messageBroker;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $paymentAccountId = $input->getArgument('paymentAccountId');
        $interval = $input->getArgument('interval');

        /** @var PaymentAccount|null $paymentAccount */
        $paymentAccount = $this->repositoryProvider->get(PaymentAccount::class)->findById($paymentAccountId);
        if (null === $paymentAccount) {
            $output->writeln('Account not found');

            return;
        }
        if ($paymentAccount->paymentSystemId !== PaymentSystem::QIWI_ID) {
            $output->writeln("It's not qiwi account");

            return;
        }

        $startDate = new DateTime("-$interval");

        $transactions = $this->fetchQiwiTransactions($paymentAccount, $startDate, $output);
        foreach ($transactions as $transaction) {
            $this->handleTransaction($transaction, $output);
        }
    }

    private function fetchQiwiTransactions(
        PaymentAccount $paymentAccount,
        DateTime $startDate,
        OutputInterface $output
    ) {
        $transactions = [];
        $nextTxnId = null;
        $nextTxnDate = null;
        $endDate = new DateTime();
        while (1) {
            $result = $this->request($output, $paymentAccount, $startDate, $endDate, $nextTxnId, $nextTxnDate);
            $nextTxnId = $result['nextTxnId'];
            $nextTxnDate = $result['nextTxnDate'];
            $transactions = array_merge($transactions, $result['data']);
            $output->writeln(count($transactions));
            if (empty($nextTxnId)) {
                break;
            }
            sleep(5);
        }

        return $transactions;
    }

    private function request(
        OutputInterface $output,
        PaymentAccount $paymentAccount,
        DateTime $startDate,
        DateTime $endDate,
        $nextTxnId = null,
        $nextTxnDate = null
    ) {
        $params = [
            'rows' => 50,
            'operation' => 'IN',
            'startDate' => $startDate->format(DateTime::W3C),
            'endDate' => $endDate->format(DateTime::W3C),
        ];
        if (null !== $nextTxnId) {
            $params['nextTxnId'] = $nextTxnId;
            $params['nextTxnDate'] = $nextTxnDate;
        }
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
        $result = json_decode($request->getBody()->getContents(), true);

        return $result;
    }

    private function handleTransaction(array $transaction, OutputInterface $output)
    {
        $output->writeln("Handle {$transaction['comment']}");
        if (1 === preg_match(Payment::PAYMENT_ID_REGEXP, $transaction['comment'], $matches)) {
            $paymentId = (int)$matches[1];
            $this->logger->debug('Transaction identified', ['paymentId' => $paymentId]);
            /** @var PaymentShot|null $paymentShot */
            $paymentShot = $this->repositoryProvider->get(PaymentShot::class)->findOneBy([
                'paymentId' => $paymentId,
                'paymentMethodId' => PaymentMethod::METHOD_QIWI_ID,
            ]);
            try {
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
                    $output->writeln('===Moved to execution');
                } else {
                    $output->writeln("Status: {$transaction['status']}. Skip executing");
                }
            } catch (PaymentManagerCheckingException
            |PaymentShotNotFoundException
            |PaymentShotNotExpectedToExecException $e) {
                $output->writeln($e->getMessage());
            }
        } else {
            $output->writeln("Transaction not identified");
        }
    }
}
