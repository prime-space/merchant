<?php namespace App\PaymentSystemManager;

use App\Constraints\PayoutReceiver;
use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotTransferException;
use App\Exception\CheckingResultRequestException;
use App\Exception\PayoutReceiverNotValidException;
use App\MessageBroker;
use App\PaymentSystemManager\Webmoney\WmSigner;
use App\TagServiceProvider\TagServiceInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bridge\Monolog\Logger;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class WebmoneyManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    InputRequestResultInterface,
    PaymentOverForm,
    PayoutInterface
{
    private const CONFIG_PURSE_KEYS = [
        PaymentMethod::METHOD_WMR_ID => 'wmr',
        PaymentMethod::METHOD_WMZ_ID => 'wmz',
        PaymentMethod::METHOD_WME_ID => 'wme',
        PaymentMethod::METHOD_WMU_ID => 'wmu',
    ];
    private const PAYMENT_REAL_MODE = '0';
    const BALANCE_KEY_R = 'wmr';
    const BALANCE_KEY_Z = 'wmz';

    private $guzzleClient;

    public function __construct(Logger $logger, Router $router, GuzzleClient $guzzleClient)
    {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
    }

    public function getTagServiceName(): string
    {
        return 'webmoney';
    }

    public function getPaymentSystemId(): int
    {
        return 6;
    }

    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        if (!isset(self::CONFIG_PURSE_KEYS[$paymentShot->paymentMethodId])) {
            throw new RuntimeException("WebmoneyManager unknown payment method #{$paymentShot->paymentMethodId}");
        }

        $formData = new FormData('https://merchant.webmoney.ru/lmi/payment.asp', FormData::METHOD_POST, [
            'LMI_PAYMENT_AMOUNT' => $paymentShot->amount,
            'LMI_PAYMENT_NO' => $paymentShot->id,
            'LMI_PAYEE_PURSE' => $paymentAccount->config[self::CONFIG_PURSE_KEYS[$paymentShot->paymentMethodId]],
            'LMI_PAYMENT_DESC_BASE64' => base64_encode($description),
            'LMI_SUCCESS_URL' => $this->compileSuccessReturnUrl($payment),
            'LMI_SUCCESS_METHOD' => 'GET',
            'LMI_FAIL_URL' => $this->compileSuccessReturnUrl($payment),
            'LMI_FAIL_METHOD' => 'GET',
        ]);

        return $formData;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('LMI_PAYMENT_NO');
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = $request->request;

        $networks = ['212.118.48.0', '212.158.173.0', '91.200.28.0', '91.227.52.0'];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        $network = substr($ip, 0, strripos($ip, '.')).'.0';
        if (!in_array($network, $networks, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputMode = $data->get('LMI_MODE');
        if ($inputMode !== self::PAYMENT_REAL_MODE) {
            throw new CheckingResultRequestException("It's test mode");
        }

        $paymentAccountPurse = $paymentAccount->config[self::CONFIG_PURSE_KEYS[$paymentShot->paymentMethodId]];
        $inputPurse = $data->get('LMI_PAYEE_PURSE');
        if ($inputPurse !== $paymentAccountPurse) {
            throw new CheckingResultRequestException("Unknown purse '$inputPurse'");
        }

        $inputAmount = $data->get('LMI_PAYMENT_AMOUNT');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $hash = strtoupper(hash(
            'sha256',
            $inputPurse.
            $inputAmount.
            $data->get('LMI_PAYMENT_NO').
            $inputMode.
            $data->get('LMI_SYS_INVS_NO').
            $data->get('LMI_SYS_TRANS_NO').
            $data->get('LMI_SYS_TRANS_DATE').
            $paymentAccount->config['secret'].
            $data->get('LMI_PAYER_PURSE').
            $data->get('LMI_PAYER_WM')
        ));
        if ($hash !== $data->get('LMI_HASH')) {
            throw new CheckingResultRequestException("Wrong hash");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['status' => 'ok']);
    }

    /** @inheritdoc */
    public function checkReceiver(string $receiver, int $accountId): void
    {
        if (preg_match('/^R[0-9]{12}$/', $receiver) !== 1) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_INCORRECT);
        }
    }

    public function getPayoutQueueName(): string
    {
        return MessageBroker::QUEUE_PAYOUT_WMR_NAME;
    }

    public function getPayoutBalanceKey(): string
    {
        return self::BALANCE_KEY_R;
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
        try {
            $signer = new WmSigner(
                $paymentAccount->config['wmid'],
                $paymentAccount->config['key'],
                $paymentAccount->config['pass']
            );
            $reqn = bcmul(microtime(true), '10000');

            $amount = rtrim(rtrim($payout->amount, '0'), '.');

            $req = new SimpleXMLElement('<w3s.request/>');
            $req->reqn = $reqn;
            $req->wmid = $paymentAccount->config['wmid'];
            $req->trans->tranid = $payout->id;
            $req->trans->pursesrc = $paymentAccount->config[$this->getPayoutBalanceKey()];
            $req->trans->pursedest = $payoutSet->receiver;
            $req->trans->amount = $amount;
            $req->trans->period = 0;
            $req->trans->pcode = '';
            $req->trans->desc = $description;
            $req->trans->wminvid = 0;
            $req->trans->onlyauth = 1;
            $req->sign = $signer->sign(
                (string) $req->reqn
                .(string) $req->trans->tranid
                .(string) $req->trans->pursesrc
                .(string) $req->trans->pursedest
                .(string) $req->trans->amount
                .(string) $req->trans->period
                .(string) $req->trans->pcode
                .(string) $req->trans->desc
                .(string) $req->trans->wminvid
            );
            $request = $this->guzzleClient->post(
                'https://w3s.webmoney.ru/asp/XMLTrans.asp',
                [
                    'timeout' => 3,
                    'connect_timeout' => 3,
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=UTF8',
                    ],
                    'body' => $req->asXML(),
                    'verify' => __DIR__ . '/Webmoney/wm.crt',
                ]
            );
            $result = new SimpleXMLElement($request->getBody()->getContents());
            $retval = isset($result->retval) ? (string) $result->retval : null;
            if ('0' !== $retval) {
                throw new CannotTransferException("retval: $retval (Expect 0)");
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotTransferException("Request code: $statusCode. Message: {$e->getMessage()}");
        }
    }

}
