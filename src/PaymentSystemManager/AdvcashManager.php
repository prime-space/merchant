<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class AdvcashManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    InputRequestResultInterface,
    PaymentOverForm
{
    public function __construct(Logger $logger, Router $router)
    {
        parent::__construct($logger, $router);
    }

    public function getTagServiceName(): string
    {
        return 'advcash';
    }

    public function getPaymentSystemId(): int
    {
        return 15;
    }

    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        $fields = [
            'ac_account_email' => $paymentAccount->config['email'],
            'ac_sci_name' => $paymentAccount->config['sciName'],
            'ac_amount' => bcadd($paymentShot->amount, '0', 2),
            'ac_currency' => 'RUR',
            'ac_order_id' => $paymentShot->id,
            'ac_ps' => $paymentMethod->externalCode,
            'ac_comments' => $description,
            'ac_success_url' => $this->compileSuccessReturnUrl($payment),
            'ac_success_url_method' => 'GET',
            'ac_fail_url' => $this->compileSuccessReturnUrl($payment),
            'ac_fail_url_method' => 'GET',
        ];
        $fields['ac_sign'] = hash('sha256', implode(':', [
            $fields['ac_account_email'],
            $fields['ac_sci_name'],
            $fields['ac_amount'],
            $fields['ac_currency'],
            $paymentAccount->config['sciSecret'],
            $fields['ac_order_id']
        ]));

        $formData = new FormData('https://wallet.advcash.com/sci', FormData::METHOD_GET, $fields);

        return $formData;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('ac_order_id');
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
            '50.7.115.5',
            '51.255.40.139',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $data->get('ac_amount');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $hash = hash('sha256', implode(':', [
            $data->get('ac_transfer'),
            $data->get('ac_start_date'),
            $data->get('ac_sci_name'),
            $data->get('ac_src_wallet'),
            $data->get('ac_dest_wallet'),
            $data->get('ac_order_id'),
            $data->get('ac_amount'),
            $data->get('ac_merchant_currency'),
            $paymentAccount->config['sciSecret']
        ]));
        if ($hash !== $data->get('ac_hash')) {
            throw new CheckingResultRequestException("Wrong sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return '';
    }
}
