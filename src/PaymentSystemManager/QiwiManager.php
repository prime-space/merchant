<?php namespace App\PaymentSystemManager;

use App\Constraints\PayoutReceiver;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\CheckingResultRequestException;
use App\Exception\PaymentManagerCheckingException;
use App\Exception\PayoutReceiverNotValidException;
use App\Exception\SkipCheckingResultRequestException;
use App\MessageBroker;
use App\PaymentAccountant;
use App\TagServiceProvider\TagServiceInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Request;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class QiwiManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverLink,
    InputRequestResultInterface,
    PayoutInterface,
    WhiteBalancingInterface
{
    const CURRENCY_ID_RUB = 643;
    const CODE_BLOCK_OUTPUT = 'QWPRC-1021';
    const CODE_NOT_ENOUGH_MONEY = 'QWPRC-220';
    const QIWI_TRANSACTION_STATUS_SUCCESS = 'SUCCESS';

    private $guzzleClient;
    private $paymentAccountant;

    public function __construct(
        Logger $logger,
        Router $router,
        GuzzleClient $guzzleClient,
        PaymentAccountant $paymentAccountant
    ) {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
        $this->paymentAccountant = $paymentAccountant;
    }

    public function getTagServiceName(): string
    {
        return 'qiwi';
    }

    public function getPaymentSystemId(): int
    {
        return 3;
    }

    /** @inheritdoc */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string {
        $amountParts = explode('.', $paymentShot->amount);
        if (count($amountParts) === 1) {
            $amountParts[1] = 0;
        } else {
            $amountParts[1] = bcmul("0.$amountParts[1]", '100', 0);
        }

        $encodedDescription = urlencode($description);

        $url =
            "https://qiwi.com/payment/form/99"
            ."?amountFraction={$amountParts[1]}"
            ."&extra%5B%27comment%27%5D=$encodedDescription"
            ."&extra%5B%27account%27%5D=%2B{$paymentAccount->config['account']}"
            ."&amountInteger={$amountParts[0]}"
            ."&currency=" . self::CURRENCY_ID_RUB
            ."&blocked[0]=sum"
            ."&blocked[1]=account"
            ."&blocked[2]=comment";

        return $url;
    }

    public function isNeedToWaitingPage(): bool
    {
        return true;
    }

    /** @throws PaymentManagerCheckingException */
    public function checkTransaction(array $transaction, PaymentShot $paymentShot)
    {
        if (1 === bccomp($paymentShot->amount, $transaction['sum']['amount'], 2)) {
            throw new PaymentManagerCheckingException(
                "Paid less. PaymentShot: {$paymentShot->amount} Paid: {$transaction['sum']['amount']}"
            );
        }

        if ($transaction['sum']['currency'] !== self::CURRENCY_ID_RUB) {
            throw new PaymentManagerCheckingException('Currency not match');
        }
    }

    /** @inheritdoc */
    public function checkReceiver(string $receiver, int $accountId): void
    {
        if (preg_match('/^\+[1-9]{1}[0-9]{3,14}$/', $receiver) !== 1) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_INCORRECT);
        }
    }

    public function getPayoutQueueName(): string
    {
        return MessageBroker::QUEUE_PAYOUT_QIWI_NAME;
    }

    /** @inheritdoc */
    public function payout(
        Payout $payout,
        PayoutSet $payoutSet,
        string $description,
        PaymentAccount $paymentAccount = null
    ): void {
        if (null === $paymentAccount) {
            throw new RuntimeException('Payment account cannot be null');
        }
        $this->transfer($paymentAccount, $payout->amount, $payoutSet->receiver, $description);
    }

    public function getPayoutBalanceKey(): string
    {
        return self::BALANCE_KEY_DEFAULT;
    }

    public function isBalanceOverBalancingPoint(PaymentAccount $paymentAccount)
    {
        return bccomp($paymentAccount->balance[self::BALANCE_KEY_DEFAULT], self::BALANCING_POINT_AMOUNT, 2) >= 0;
    }

    /** @inheritdoc */
    public function transfer(
        PaymentAccount $paymentAccount,
        string $amount,
        string $receiver,
        string $description,
        string $label = null
    ): array {
        try {
            $id = bcmul(microtime(true), '10000');
            $request = $this->guzzleClient->request(
                'POST',
                'https://edge.qiwi.com/sinap/api/v2/terms/99/payments',
                [
                    'timeout' => 30,
                    'connect_timeout' => 30,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$paymentAccount->config['token']}",
                    ],
                    'json' => [
                        'id' => $id,
                        'sum' => [
                            'amount' => (float)$amount,
                            'currency' => (string)self::CURRENCY_ID_RUB,
                        ],
                        'paymentMethod' => [
                            'type' => 'Account',
                            'accountId' => (string)self::CURRENCY_ID_RUB,
                        ],
                        'fields' => [
                            'account' => $receiver,
                        ],
                    ]
                ]
            );
            $result = json_decode($request->getBody()->getContents(), true);
            $isAccepted =
                isset($result['transaction']['state']['code'])
                && $result['transaction']['state']['code'] === 'Accepted';
            if (!$isAccepted) {
                throw new CannotTransferException();
            }

            return [];
        } catch (ConnectException $e) {
            throw new CannotTransferTimeoutException("{$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            $statusCode = null;
            $code = null;
            $response = $e->getResponse();
            if (null !== $response) {
                $statusCode = $response->getStatusCode();
                $content = json_decode($response->getBody()->getContents(), true);
                if (isset($content['code'])) {
                    $code = $content['code'];
                }
            }
            if ($code === self::CODE_NOT_ENOUGH_MONEY) {
                $this->paymentAccountant->dropBalance($paymentAccount);
            } elseif ($code === self::CODE_BLOCK_OUTPUT) {
                $this->paymentAccountant->deactivateAccount($paymentAccount, $code);
            }

            throw new CannotTransferException("Request code: $statusCode. Code: $code. Message: {$e->getMessage()}");
        }
    }

    public function getAccountReceiver(PaymentAccount $paymentAccount): string
    {
        return $paymentAccount->config['account'];
    }

    /** @inheritdoc */

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        $data = json_decode($request->getContent(), true);
        $paymentShotId = $data['bill']['billId'];

        return $paymentShotId;
    }

    /** @inheritdoc */

    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $params = json_decode($request->getContent(), true);
        $status = $params['bill']['status']['value'];
        if ($status !== 'PAID') {
            throw new SkipCheckingResultRequestException("Status '$status'");
        }
        $inputAmount = $params['bill']['amount']['value'];

        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }
        $currency = $params['bill']['amount']['currency'];

        if ($currency !== 'RUB') {
            throw new CheckingResultRequestException("Currency '$currency'");
        }

    }
    /** @inheritdoc */

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['error' => '0']);
    }
}
