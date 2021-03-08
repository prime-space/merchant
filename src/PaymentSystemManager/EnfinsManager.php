<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotBuildLinkUrlException;
use App\Exception\CheckingResultRequestException;
use App\Exception\SkipCheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class EnfinsManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    InputRequestResultInterface,
    PaymentOverLink
{
    private $guzzleClient;

    public function __construct(Logger $logger, Router $router, GuzzleClient $guzzleClient)
    {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
    }

    public function getTagServiceName(): string
    {
        return 'enfins';
    }

    public function getPaymentSystemId(): int
    {
        return 19;
    }

    public function isNeedToWaitingPage(): bool
    {
        return true;
    }

    /** @inheritdoc */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string {
        $amount = bcadd($paymentShot->amount, '0', 2);
        $params = [
            'ident' => $paymentAccount->config['ident'],
            'currency' => 'RUB',
            'amount' => $amount,
            'description' => $description,
            'm_order' => $paymentShot->id,
            'p_method' => $paymentMethod->externalCode,
        ];
        $params['sign'] = hash_hmac('sha1', http_build_query($params), $paymentAccount->config['secret']);
        $request = $this->guzzleClient->post(
            'https://api.hotpay.money/v1/create_bill',
            [
                'form_params' => $params,
                'timeout' => 6,
                'connect_timeout' => 6,
            ]
        );
        $result = json_decode($request->getBody()->getContents(), true);
        if (!isset($result['result']) || $result['result'] !== true) {
            throw new CannotBuildLinkUrlException("['result'] is not true");
        }
        $url = $result['data']['url'];

        return $url;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return (int)$request->request->get('m_order');
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $params = $request->request->all();

        $status = $params['status'];
        if ($status !== 'paid') {
            throw new SkipCheckingResultRequestException("Status '$status'");
        }

        $ips = [
            '34.240.179.184',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $params['amount'];
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $currency = $params['currency'];
        if ($currency !== 'RUB') {
            throw new CheckingResultRequestException("Currency '$currency'");
        }

        if ($params['testing'] === 'true') {
            throw new CheckingResultRequestException("Test mode");
        }

        $sign = $this->calcSignature($paymentAccount, $params);
        if ($sign !== $params['sign']) {
            throw new CheckingResultRequestException("Wrong sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return 'OK';
    }

    private function calcSignature(PaymentAccount $paymentAccount, array $params): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);
        $sign = hash_hmac('sha1', http_build_query($params), $paymentAccount->config['secret']);

        return $sign;
    }
}
