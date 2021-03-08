<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Symfony\Component\HttpFoundation\Request;

class InterkassaManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    InputRequestResultInterface,
    PaymentOverForm
{
    public function getTagServiceName(): string
    {
        return 'interkassa';
    }

    public function getPaymentSystemId(): int
    {
        return 5;
    }

    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        $fields = [
            'ik_co_id' => $paymentAccount->config['id'],
            'ik_pm_no' => $paymentShot->id,
            'ik_am' => $paymentShot->amount,
            'ik_cur' => 'RUB',
            'ik_desc' => $description,
            'ik_act' => 'process',
            'ik_pw_via' => $paymentMethod->externalCode,
            'ik_suc_u' => $this->compileSuccessReturnUrl($payment),
            'ik_suc_m' => 'get',
            'ik_pnd_u' => $this->compileSuccessReturnUrl($payment),
            'ik_pnd_m' => 'get',
            'ik_fal_u' => $this->compileSuccessReturnUrl($payment),
            'ik_fal_m' => 'get',
        ];
        $fields['ik_sign'] = $this->calcPaymentInitHash($paymentAccount->config['key'], $fields);

        $formData = new FormData('https://sci.interkassa.com/', FormData::METHOD_POST, $fields);

        return $formData;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('ik_pm_no');
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = $request->request;

        if ($data->get('ik_co_id') !== $paymentAccount->config['id']) {
            throw new CheckingResultRequestException("ik_co_id does not match");
        }

        if ($data->get('ik_inv_st') !== 'success') {
            throw new CheckingResultRequestException(
                "ik_inv_st haven't value 'success' ('{$data->get('ik_inv_st')}'')"
            );
        }

        $ips = [
            '151.80.190.97',
            '151.80.190.98',
            '151.80.190.99',
            '151.80.190.100',
            '151.80.190.101',
            '151.80.190.102',
            '151.80.190.103',
            '151.80.190.104',
            '151.80.190.105',
            '151.80.190.106',
            '151.80.190.107',
            '35.233.69.55',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $data->get('ik_am');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $hash = $this->calcPaymentInitHash($paymentAccount->config['key'], $data->all());
        if ($hash !== $data->get('ik_sign')) {
            throw new CheckingResultRequestException("Wrong ik_sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return '';
    }

    private function calcPaymentInitHash(string $key, array $data)
    {
        unset($data['ik_sign']);
        ksort($data, SORT_STRING);
        array_push($data, $key);
        $sign = implode(':', $data);
        $sign = base64_encode(hash('sha256', $sign, true));

        return $sign;
    }
}
