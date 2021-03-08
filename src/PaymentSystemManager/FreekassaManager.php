<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Symfony\Component\HttpFoundation\Request;

class FreekassaManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverLink,
    InputRequestResultInterface
{
    const PAYMENT_FORM_URL = 'http://www.free-kassa.ru/merchant/cash.php';

    public function getTagServiceName(): string
    {
        return 'freekassa';
    }

    public function getPaymentSystemId(): int
    {
        return 12;
    }

    /** @inheritdoc */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string {
        $amount = bcadd($paymentShot->amount, 0, 2);
        $signParts = [
            $paymentAccount->config['merchantId'],
            $amount,
            $paymentAccount->config['secret'],
            $paymentShot->id,
        ];

        $sign = md5(implode(':', $signParts));

        $fields = [
            'm' => $paymentAccount->config['merchantId'],
            'oa' => $amount,
            'o' => $paymentShot->id,
            'i' => $paymentMethod->externalCode,
            's' => $sign,
        ];

        if (!empty($payment->email)) {
            $fields['em'] = $payment->email;
        }

        $url = self::PAYMENT_FORM_URL . '?' . http_build_query($fields);

        return $url;
    }

    public function isNeedToWaitingPage(): bool
    {
        return true;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->get('MERCHANT_ORDER_ID');
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = $request->request;

        $ips = [
            '136.243.38.147',
            '136.243.38.149',
            '136.243.38.150',
            '136.243.38.151',
            '136.243.38.189',
            '88.198.88.98',
            '136.243.38.108',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $data->get('AMOUNT');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $signParts = [
            $data->get('MERCHANT_ID'),
            $data->get('AMOUNT'),
            $paymentAccount->config['secret2'],
            $data->get('MERCHANT_ORDER_ID'),
        ];
        $sign = md5(implode(':', $signParts));
        if ($data->get('SIGN') !== $sign) {
            throw new CheckingResultRequestException('Wrong sign');
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return 'YES';
    }
}
