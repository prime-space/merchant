<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotCompileSpecialHtmlException;
use App\Exception\CannotInitPaymentException;
use App\Exception\CheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class GamemoneyManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverSpecialHtml,
    PreInitPaymentInterface,
    InputRequestResultInterface
{
    const API_PAYGATE_URL = 'https://paygate.gamemoney.com/';
    const API_PAYGATE_METHOD_INVOICE = 'invoice';
    const API_PAYGATE_METHOD_PAYOUT = 'checkout/insert';

    const URL_PAY_TERMINAL = 'https://pay.gamemoney.com/terminal/';

    private $guzzleClient;

    public function __construct(
        Logger $logger,
        Router $router,
        GuzzleClient $guzzleClient
    ) {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
    }

    public function getTagServiceName(): string
    {
        return 'gamemoney';
    }

    public function getPaymentSystemId(): int
    {
        return 14;
    }

    /** @inheritdoc */
    public function preInitPayment(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): array {
        $fields = [
            'project' => $paymentAccount->config['projectId'],
            'type' => 'card_eur',
            'user' => 1,
            'ip' => $payment->ip,
            'amount' => bcadd($paymentShot->amount, 0, 2),
            'comment' => $description,
            'success_url' => $this->compileSuccessReturnUrl($payment),
            'fail_url' => $this->compileFailReturnUrl($payment),
            'project_invoice' => $paymentShot->id,
            'wallet' => '',
            'currency' => 'RUB',
        ];

        $fieldsString = $this->compileFieldsString($fields);
        $fields['signature'] = hash_hmac('sha256', $fieldsString, $paymentAccount->config['hmacKey']);

        $this->logger->info('Init data', $fields);

        try {
            $request = $this->guzzleClient->post(self::API_PAYGATE_URL . self::API_PAYGATE_METHOD_INVOICE, [
                'timeout' => 6,
                'connect_timeout' => 6,
                'form_params' => $fields,
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            //$statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotInitPaymentException('Init request');
        }
        $content = json_decode($request->getBody()->getContents(), true);
        if (empty($content['state']) || $content['state'] !== 'success') {
            throw new CannotInitPaymentException('Init response state', $content);
        }

        return ['action' => $content['data']];
    }

    /** @inheritdoc */
    public function getSpecialHtml(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): string {
        try {
            $request = $this->guzzleClient
                ->get($paymentShot->initData['action'], ['timeout' => 6, 'connect_timeout' => 6,]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotCompileSpecialHtmlException(
                'First page request',
                ['payment' => $payment->id, 'code' => $statusCode, 'message' => $e->getMessage()]
            );
        }
        $content = $request->getBody()->getContents();

        if (1 !== preg_match('/\<form action\=\"https\:\/\/pay\.gamemoney\.com.*\<\/form\>/sm', $content, $matches)) {
            throw new CannotCompileSpecialHtmlException('Form not matched', ['payment' => $payment->id]);
        }
        $formHtml = $matches[0];

        return $formHtml;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('project_invoice');
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
            '51.254.86.74',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        if ($data->get('status') !== 'Paid') {
            throw new CheckingResultRequestException("Status is not success ('{$data->get('m_status')}')");
        }

        $inputAmount = $data->get('amount');
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $fieldsString = $this->compileFieldsString($data->all());
        $pkey = openssl_pkey_get_public('file://'.__DIR__.'/Gamemoney/gm.crt');
        $rawSignature = base64_decode($data->get('signature'));
        if (1 !== openssl_verify($fieldsString, $rawSignature, $pkey, OPENSSL_ALGO_SHA256)) {
            throw new CheckingResultRequestException('Signature not verified');
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['success' => 'true']);
    }

    public function rawPayout(
        PaymentAccount $paymentAccount,
        string $projectId,
        string $type,
        string $wallet,
        string $amount,
        string $currency
    ) {
        $fields = [
            'project' => $paymentAccount->config['projectId'],
            'projectId' => $projectId,
            'user' => 1,
            'ip' => '88.198.113.154',
            'amount' => $amount,
            'wallet' => $wallet,
            'description' => 'Raw payout',
            'type' => $type,
            'currency' => $currency,
        ];
        $fieldsString = $this->compileFieldsString($fields);
        $pkey = openssl_pkey_get_private(base64_decode($paymentAccount->config['rsaKey']));
        $signature = '';
        if (true !== openssl_sign($fieldsString, $signature, $pkey, OPENSSL_ALGO_SHA256)) {
            $error = 'Cannot generate signature';
            $this->logger->error($error);

            throw new RuntimeException($error);
        }

        $fields['signature'] = base64_encode($signature);
        $this->logger->info('Raw payout', $fields);
        try {
            $request = $this->guzzleClient->post(self::API_PAYGATE_URL . self::API_PAYGATE_METHOD_PAYOUT, [
                'timeout' => 6,
                'connect_timeout' => 6,
                'form_params' => $fields,
            ]);
            $result = json_decode($request->getBody()->getContents(), true);
            $this->logger->info('Raw payout result', $result);
        } catch (RequestException $e) {
            $error = "Raw payout error: {$e->getMessage()}";
            $this->logger->error($error);

            throw new RuntimeException($error);
        }
    }

    private function compileFieldsString(array $fields): string
    {
        unset($fields['signature']);
        ksort($fields, SORT_STRING);

        $fieldStrings = [];
        foreach ($fields as $key => $value) {
            $fieldStrings[] = $key === 'terminal_allow_methods[]'
                ? "terminal_allow_methods:0:$value;;"
                : "$key:$value;";
        }
        $fieldsString = implode('', $fieldStrings);

        return $fieldsString;
    }
}
