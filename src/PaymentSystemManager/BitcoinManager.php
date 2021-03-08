<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\PaymentSystem;
use App\Exception\BitcoinApiRequestException;
use App\Exception\CannotInitPaymentException;
use App\MessageBroker;
use App\Repository\PaymentShotRepository;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use LogicException;
use Skies\QRcodeBundle\Generator\Generator as BarcodeGenerator;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class BitcoinManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PreInitPaymentInterface,
    PaymentOverSpecialWaitingPage
{
    private const API_METHOD_GET_RAW_ADDRESS = 'getrawchangeaddress';
    private const API_METHOD_LIST_UNSPENT = 'listunspent';
    private const API_ADDRESS_TYPE_SEGWIT = 'p2sh-segwit';

    private const TRANSACTION_LIFETIME_MINUTES = 1440;//day
    private const TRANSACTION_MAX_CONFIRMATIONS = 9999999;

    private $guzzle;
    private $barcodeGenerator;
    private $repositoryProvider;
    private $messageBroker;

    public function __construct(
        MessageBroker $messageBroker,
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        Router $router,
        Guzzle $guzzle,
        BarcodeGenerator $barcodeGenerator
    ) {
        parent::__construct($logger, $router);
        $this->guzzle = $guzzle;
        $this->barcodeGenerator = $barcodeGenerator;
        $this->repositoryProvider = $repositoryProvider;
        $this->messageBroker = $messageBroker;
    }

    public function getTagServiceName(): string
    {
        return 'bitcoin';
    }

    public function getPaymentSystemId(): int
    {
        return 10;
    }

    /** @inheritdoc */
    public function preInitPayment(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): array {
        $this->logger->info("Pre init payment {$paymentShot->paymentId}", [
            'paymentShotId' => $paymentShot->id,
            'paymentMethodId' => $paymentMethod->id,
        ]);
        try {
            $address = $this->bitcoinApiRequest(
                $paymentAccount->config['ip'],
                $paymentAccount->config['user'],
                $paymentAccount->config['pass'],
                self::API_METHOD_GET_RAW_ADDRESS,
                [self::API_ADDRESS_TYPE_SEGWIT]
            );
            $qrCode = $this->generateQrCode($address, $paymentShot->amount);
            $data = [
                'address' => $address,
                'qrCode' => $qrCode,
                'requiredConfirmations' => $this->calcRequiredConfirmations($paymentShot),
                'confirmations' => null,
            ];

            return $data;
        } catch (BitcoinApiRequestException $e) {
            $this->logger->crit("BitcoinApiRequestException while get address: {$e->getMessage()}");

            throw new CannotInitPaymentException();
        }
    }

    /** @throws BitcoinApiRequestException */
    private function bitcoinApiRequest(string $host, string $user, string $pass, string $method, array $params = [])
    {
        $url = "http://{$host}:8332";
        $body = json_encode(['jsonrpc' => '1.0', 'id' => 'curltest', 'method' => $method, 'params' => $params]);
        $auth = [$user, $pass];
        try {
            $response = $this->guzzle->post($url, [
                'body' => $body,
                'auth' => $auth,
                'timeout' => 15,
                'connect_timeout' => 15,
            ])->getBody();
            $responseData = json_decode($response, true);
            if (!array_key_exists('error', $responseData) || null !== $responseData['error']) {
                throw new BitcoinApiRequestException('Error not null');
            }

            return $responseData['result'];
        } catch (RequestException $e) {
            throw new BitcoinApiRequestException('Guzzle request exception');
        }
    }

    public function getWaitingPageData(PaymentShot $paymentShot): WaitingPageData
    {
        if (isset($paymentShot->initData['confirmations'])) {
            $type = WaitingPageData::TYPE_BITCOIN_CONFIRMATIONS;
            $data = [
                'requiredConfirmations' => $paymentShot->initData['requiredConfirmations'],
                'confirmations' => $paymentShot->initData['confirmations'],
            ];
        } else {
            $type = WaitingPageData::TYPE_BITCOIN_ADDRESS;
            $qrCode = $paymentShot->initData['qrCode']
                ?? $this->generateQrCode($paymentShot->initData['address'], $paymentShot->amount);
            $data = [
                'address' => $paymentShot->initData['address'],
                'amount' => $paymentShot->amount,
                'qrCode' => $qrCode,
            ];
        }

        $waitingPageData = new WaitingPageData($type, $data);

        return $waitingPageData;
    }

    /** TODO partitioning */
    public function checkTransactions(Logger $logger)
    {
        /** @var PaymentShotRepository $paymentShotRepository */
        $paymentShotRepository = $this->repositoryProvider->get(PaymentShot::class);
        $paymentShots = $paymentShotRepository
            ->getLastShotsByPaymentMethodId(self::TRANSACTION_LIFETIME_MINUTES, 0, PaymentMethod::METHOD_BITCOIN_ID);
        $bitcoinPaymentAccount = $this->getBitcoinPaymentAccountForCheckingTransactions($logger);
        /** @var PaymentShot[] $paymentShotIndexedByAddresses */
        $paymentShotIndexedByAddresses = [];
        foreach ($paymentShots as $paymentShot) {
            $paymentShotIndexedByAddresses[$paymentShot->initData['address']] = $paymentShot;
        }
        $addresses = array_keys($paymentShotIndexedByAddresses);
        $addressesNum = count($addresses);
        $logger->info("Found $addressesNum addresses for checking");
        $addressesChunks = array_chunk($addresses, 100);
        $transactions = [];
        try {
            foreach ($addressesChunks as $addressesChunk) {
                $transactionsPart = $this->bitcoinApiRequest(
                    $bitcoinPaymentAccount->config['ip'],
                    $bitcoinPaymentAccount->config['user'],
                    $bitcoinPaymentAccount->config['pass'],
                    self::API_METHOD_LIST_UNSPENT,
                    [0, self::TRANSACTION_MAX_CONFIRMATIONS, $addressesChunk]
                );
                $transactions = array_merge($transactions, $transactionsPart);
            }
        } catch (BitcoinApiRequestException $e) {
            $logger->crit("BitcoinApiRequestException while checking transactions: {$e->getMessage()}");

            return;
        }
        $transactionsNum = count($transactions);
        if ($transactionsNum > 0) {
            $logger->info("Found $transactionsNum transactions");
        }
        foreach ($transactions as $transaction) {
            $paymentShot = $paymentShotIndexedByAddresses[$transaction['address']];
            $amount = number_format($transaction['amount'], 8, '.', '');
            if (1 === bccomp($paymentShot->amount, $amount, 8)) {
                $logger->error("#$paymentShot->paymentId Paid less. PaymentShot: $paymentShot->amount Paid: $amount");

                continue;
            }
            if (!isset($paymentShot->initData['requiredConfirmations'])) {
                $paymentShot->initData['requiredConfirmations'] = $this->calcRequiredConfirmations($paymentShot);
                $paymentShot->initData['confirmations'] = null;
                $paymentShotRepository->update($paymentShot);
            }
            if ($paymentShot->initData['confirmations'] !== $transaction['confirmations']) {
                $paymentShot->initData['confirmations'] = $transaction['confirmations'];
                $paymentShotRepository->update($paymentShot);
                $this->logger->info("#$paymentShot->paymentId {$transaction['confirmations']} confirmations");
            }

            if ($paymentShot->initData['confirmations'] >= $paymentShot->initData['requiredConfirmations']) {
                $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
                    'paymentShotId' => $paymentShot->id
                ]);
                $this->logger->info("#$paymentShot->paymentId moved to execution");
            }
        }
    }

    private function getBitcoinPaymentAccountForCheckingTransactions(Logger $logger): PaymentAccount
    {
        $bitcoinPaymentAccounts = $this->repositoryProvider->get(PaymentAccount::class)
            ->findBy(['paymentSystemId' => PaymentSystem::BITCOIN_ID]);
        $bitcoinPaymentAccountsNum = count($bitcoinPaymentAccounts);
        if ($bitcoinPaymentAccountsNum !== 1) {
            $error = "Found $bitcoinPaymentAccountsNum bitcoin accounts, expect 1";
            $logger->crit($error);

            throw new LogicException($error);
        }
        $bitcoinPaymentAccount = $bitcoinPaymentAccounts[0];

        return $bitcoinPaymentAccount;
    }

    private function calcRequiredConfirmations(PaymentShot $paymentShot)
    {
        $confirmations = 6;
        $scales = [
            ['threshold' => '0.005', 'confirmations' => 1],
            ['threshold' => '0.02',  'confirmations' => 2],
            ['threshold' => '0.2',   'confirmations' => 3],
            ['threshold' => '1',     'confirmations' => 4],
            ['threshold' => '4',     'confirmations' => 5],
        ];
        foreach ($scales as $scale) {
            if (bccomp($paymentShot->amount, $scale['threshold'], 8) === -1) {
                $confirmations = $scale['confirmations'];

                break;
            }
        }

        return $confirmations;
    }

    private function generateQrCode(string $address, string $amount): string
    {
        $qrCode = $this->barcodeGenerator->generate([
            'code' => "bitcoin:$address?amount=$amount",
            'type' => 'qrcode',
            'format' => 'png',
            'width' => 5,
            'height' => 5,
            'color' => [127, 127, 127],
        ]);

        return $qrCode;
    }
}
