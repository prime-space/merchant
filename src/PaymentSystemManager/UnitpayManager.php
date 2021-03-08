<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CheckingResultRequestException;
use App\Exception\SkipCheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class UnitpayManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    InputRequestResultInterface,
    PaymentOverLink
{
    public function __construct(Logger $logger, Router $router)
    {
        parent::__construct($logger, $router);
    }

    public function getTagServiceName(): string
    {
        return 'unitpay';
    }

    public function getPaymentSystemId(): int
    {
        return 18;
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
        $signature = hash('sha256', implode('{up}', [
            $paymentShot->id,
            $description,
            $amount,
            $paymentAccount->config['projectSecretKey'],
        ]));

        $encodedDescription = urlencode($description);

        $url =
            "https://unitpay.ru/pay/{$paymentAccount->config['projectPublicKey']}/{$paymentMethod->externalCode}"
            ."?sum={$amount}"
            ."&account=$paymentShot->id"
            ."&desc={$encodedDescription}"
            ."&signature={$signature}";

        return $url;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return (int)$request->query->get('params')['account'];
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $params = $request->query->get('params');

        $method = $request->query->get('method');
        if (in_array($method, ['check', 'error'], true)) {
            throw new SkipCheckingResultRequestException("Method '{$method}'");
        }
        if ($method !== 'pay') {
            throw new CheckingResultRequestException("Method '{$method}'");
        }

        $ips = [
            '31.186.100.49',
            '178.132.203.105',
            '52.29.152.23',
            '52.19.56.234',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $params['orderSum'];
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $currency = $params['orderCurrency'];
        if ($currency !== 'RUB') {
            throw new CheckingResultRequestException("Currency '$currency'");
        }

        if ($params['test'] !== '0') {
            throw new CheckingResultRequestException("Test mode");
        }

        if ($this->calcSignature($paymentAccount, $params, $method) !== $params['signature']) {
            throw new CheckingResultRequestException("Wrong sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['result' => ['message' => 'Запрос успешно обработан']]);
    }

    private function calcSignature(PaymentAccount $paymentAccount, array $params, string $method): string
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $paymentAccount->config['projectSecretKey']);
        array_unshift($params, $method);
        $sign = hash('sha256', join('{up}', $params));

        return $sign;
    }
}
