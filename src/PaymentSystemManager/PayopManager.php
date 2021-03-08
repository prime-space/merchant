<?php namespace App\PaymentSystemManager;

use App\Entity\Currency;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotBuildLinkUrlException;
use App\Exception\CheckingResultRequestException;
use App\Exception\SkipCheckingResultRequestException;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class PayopManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverLink,
    InputRequestResultInterface
{
    private $guzzleClient;
    private $repositoryProvider;

    public function __construct(
        Logger $logger,
        Router $router,
        GuzzleClient $guzzleClient,
        RepositoryProvider $repositoryProvider
    ) {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function getTagServiceName(): string
    {
        return 'payop';
    }

    public function getPaymentSystemId(): int
    {
        return 16;
    }

    /** @inheritdoc */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string {
        if (empty($payment->email)) {
            throw new RuntimeException('Email required for payop');
        }

        /** @var Currency $currency */
        $currency = $this->repositoryProvider->get(Currency::class)->findById($paymentMethod->currencyId);
        $paramCurrency = strtoupper($currency->name);

        $data = [
            'publicKey' => $paymentAccount->config['publicKey'],
            'order' => [
                'id' => $paymentShot->id,
                'amount' => bcadd($paymentShot->amount, 0, 4),
                'currency' => $paramCurrency,
                'description' => $description,
            ],
            'customer' => [
                'email' => $payment->email,
            ],
            'paymentMethod' => $paymentMethod->externalCode,
            'resultUrl' => $this->compileSuccessReturnUrl($payment),
            'failUrl' => $this->compileFailReturnUrl($payment),
        ];
        $data['signature'] = $this->compileSignature($paymentAccount->config['secretKey'], $data);

        try {
            $request = $this->guzzleClient->post('https://payop.com/api/v1.1/payments/payment', [
                RequestOptions::JSON => $data,
            ]);
            $content = json_decode($request->getBody()->getContents(), true);
            if (isset($content['data']['redirectUrl'])) {
                return $content['data']['redirectUrl'];
            } else {
                $error = isset($content['errors']['code']) ? $content['errors']['code'] : 'unknown error';

                throw new CannotBuildLinkUrlException($error);
            }
        } catch (RequestException $e) {
            throw new CannotBuildLinkUrlException($e->getMessage());
        }
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        $data = json_decode($request->getContent(), true);

        return $data['orderId'];
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = json_decode($request->getContent(), true);

        if ($data['status'] !== 'success') {
            throw new SkipCheckingResultRequestException("Status '{$data['status']}'");
        }

        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($paymentShot->paymentMethodId);
        /** @var Currency $currency */
        $currency = $this->repositoryProvider->get(Currency::class)->findById($paymentMethod->currencyId);
        $paramCurrency = strtoupper($currency->name);

        if ($data['currency'] !== $paramCurrency) {
            throw new CheckingResultRequestException("Wrong currency '{$data['currency']}'");
        }

        $inputAmount = $data['amount'];
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $signatureData = ['order' => [
            'id' => $data['orderId'],
            'amount' => $data['amount'],
            'currency' => $data['currency'],
        ]];
        $signature = $this->compileSignature($paymentAccount->config['secretKey'], $signatureData, $data['status']);
        if ($data['signature'] !== $signature) {
            throw new CheckingResultRequestException("Wrong sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return '';
    }

    private function compileSignature(string $secretKey, array $data, string $status = null): string
    {
        $signatureData = [
            'id' => $data['order']['id'],
            'amount' => $data['order']['amount'],
            'currency' => $data['order']['currency'],
        ];
        ksort($signatureData, SORT_STRING);
        $signatureDataSet = array_values($signatureData);
        if (null !== $status) {
            array_push($signatureDataSet, $status);
        }
        array_push($signatureDataSet, $secretKey);

        $signature = hash('sha256', implode(':', $signatureDataSet));

        return $signature;
    }
}
