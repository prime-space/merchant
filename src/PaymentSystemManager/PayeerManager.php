<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Shop;
use App\Exception\CheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class PayeerManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverForm,
    InputRequestResultInterface
{
    const CURRENCY_RUB_NAME = 'RUB';

    private $repositoryProvider;

    public function __construct(Logger $logger, Router $router, RepositoryProvider $repositoryProvider)
    {
        parent::__construct($logger, $router);
        $this->repositoryProvider = $repositoryProvider;
    }

    public function getTagServiceName(): string
    {
        return 'payeer';
    }

    public function getPaymentSystemId(): int
    {
        return 7;
    }

    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        $description = base64_encode($description);
        $amount = bcadd($paymentShot->amount, 0, 2);

        $arHash = [
            $paymentAccount->config['shopId'],
            $paymentShot->id,
            $amount,
            self::CURRENCY_RUB_NAME,
            $description
        ];

        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);
        $shopDomain = parse_url($shop->url, PHP_URL_HOST);

        $arParams = [
            'submerchant' => $shopDomain,
            'success_url' => $this->compileSuccessReturnUrl($payment),
            'fail_url' => $this->compileFailReturnUrl($payment),
        ];
        $key = md5($paymentAccount->config['addKey'].$paymentShot->id);
        $params = @urlencode(
            base64_encode(openssl_encrypt(json_encode($arParams), 'AES-256-CBC', $key, OPENSSL_RAW_DATA))
        );
        $arHash[] = $params;
        $arHash[] = $paymentAccount->config['key'];
        $sign = strtoupper(hash('sha256', implode(':', $arHash)));

        $formData = new FormData('https://payeer.com/merchant/', FormData::METHOD_POST, [
            'm_shop' => $paymentAccount->config['shopId'],
            'm_orderid' => $paymentShot->id,
            'm_amount' => $amount,
            'm_curr' => self::CURRENCY_RUB_NAME,
            'm_desc' => $description,
            'm_params' => $params,
            'm_sign' => $sign,
            'm_cipher_method' => 'AES-256-CBC',
            'form[ps]' => $paymentMethod->externalCode,
            'form[curr['.$paymentMethod->externalCode.']]' => self::CURRENCY_RUB_NAME,
        ]);

        return $formData;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('m_orderid');
    }

    /** @throws CheckingResultRequestException */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = $request->request;

        $ips = [
            '185.71.65.92',
            '185.71.65.189',
            '149.202.17.210',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $arHash = [
            $data->get('m_operation_id'),
            $data->get('m_operation_ps'),
            $data->get('m_operation_date'),
            $data->get('m_operation_pay_date'),
            $data->get('m_shop'),
            $data->get('m_orderid'),
            $data->get('m_amount'),
            $data->get('m_curr'),
            $data->get('m_desc'),
            $data->get('m_status'),
            $data->get('m_params'),
            $paymentAccount->config['key'],
        ];
        $signHash = strtoupper(hash('sha256', implode(':', $arHash)));

        if ($_POST['m_sign'] !== $signHash) {
            throw new CheckingResultRequestException('Wrong sign');
        }

        if ($data->get('m_status') !== 'success') {
            throw new CheckingResultRequestException("Status is not success ('{$data->get('m_status')}')");
        }

        $inputAmount = $data->get('m_amount');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return "{$paymentShot->id}|success";
    }
}
