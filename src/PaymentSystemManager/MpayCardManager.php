<?php namespace App\PaymentSystemManager;

use App\Constraints\PayoutReceiver;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotCheckException;
use App\Exception\CannotCompileFormDataException;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\CheckingResultRequestException;
use App\Exception\MakeFormException;
use App\Exception\PayoutReceiverNotValidException;
use App\Exception\SelfFormHandlingException;
use App\Exception\SkipCheckingResultRequestException;
use App\MessageBroker;
use App\PaymentAccountant;
use App\TagServiceProvider\TagServiceInterface;
use DOMDocument;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MpayCardManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverForm,
//    PaymentOverSelfForm,
    InputRequestResultInterface,
    PayoutInterface,
    PayoutWithChecking
{
    const API_INIT_FORM_URL = 'https://api.mpay.ru/init_form';

    private $guzzle;
    private $repositoryProvider;
    private $translator;
    private $validator;
    private $paymentAccountant;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        Router $router,
        TranslatorInterface $translator,
        Guzzle $guzzle,
        ValidatorInterface $validator,
        PaymentAccountant $paymentAccountant
    ) {
        parent::__construct($logger, $router);
        $this->guzzle = $guzzle;
        $this->repositoryProvider = $repositoryProvider;
        $this->translator = $translator;
        $this->validator = $validator;
        $this->paymentAccountant = $paymentAccountant;
    }

    public function getTagServiceName(): string
    {
        return 'mpay_card';
    }

    public function getPaymentSystemId(): int
    {
        return 17;
    }


    /** @inheritdoc */
    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        $label = sprintf('%s-%s', $paymentShot->id, time());
        $data = [
            'partner_id' => $paymentAccount->config['partnerId'],
            'project_id' => $paymentAccount->config['projectId'],
            'amount' => bcadd($paymentShot->amount, 0, 2),
            'description' => $description,
            'success_url' => $this->compileSuccessReturnUrl($payment),
            'failure_url' => $this->compileFailReturnUrl($payment),
            'user_data' => $label,
        ];
        ksort($data, SORT_STRING);
        $dataStrings = [];
        foreach ($data as $key => $value) {
            $dataStrings[] = "$key=$value";
        }
        $dataString = implode('&', $dataStrings);
        $data['sign'] = md5("init_form{$dataString}{$paymentAccount->config['apiKey']}");

        try {
            $requestId = 'init';
            $request = $this->guzzle->get(self::API_INIT_FORM_URL, [
                'query' => $data,
                'timeout' => 6,
                'connect_timeout' => 6,
            ]);
            $responseData = json_decode($request->getBody()->getContents(), true);
            if (empty($responseData['url'])) {
                throw new CannotCompileFormDataException("Request $requestId. No URL");
            }

            $formData = new FormData($responseData['url'], FormData::METHOD_GET, []);

            return $formData;
        } catch (RequestException $e) {
            $requestCode = null;
            $response = $e->getResponse();
            if (null !== $response) {
                $responseCode = $response->getStatusCode();
                $responseContent = $response->getBody()->getContents();
            }

            throw new CannotCompileFormDataException("Request $requestId. code: $responseCode, content: $responseContent");
        }
    }

//    public function getSelfFormType(): string
//    {
//        return PaymentOverSelfForm::FORM_TYPE_CARD;
//    }
//
//    /** @inheritdoc */
//    public function handleSelfForm(
//        Payment $payment,
//        PaymentShot $paymentShot,
//        PaymentAccount $paymentAccount,
//        array $requestData,
//        string $description
//    ): ?FormData {
//        @TODO VALIDATE CARD DATA???
//        try {
//            $formData = $this->makePaymentForm(
//                $payment,
//                $paymentShot,
//                $paymentAccount,
//                $description,
//                $requestData['number'],
//                $requestData['holder'],
//                $requestData['month'],
//                $requestData['year'],
//                $requestData['cvc']
//            );
//        } catch (MakeFormException $e) {
//            throw new SelfFormHandlingException($e->getMessage(), 0, $e);
//        }
//
//        return $formData;
//    }

    /** @throws MakeFormException */
    public function makePaymentForm(
        Payment $payment,
        PaymentShot $paymentShot,
        PaymentAccount $paymentAccount,
        string $description,
        string $number,
        string $holder,
        string $month,
        string $year,
        string $cvc
    ): FormData {
        $label = sprintf('%s-%s', $paymentShot->id, time());
        $data = [
            'partner_id' => $paymentAccount->config['partnerId'],
            'project_id' => $paymentAccount->config['projectId'],
            'amount' => bcadd($paymentShot->amount, 0, 2),
            'description' => $description,
            'success_url' => $this->compileSuccessReturnUrl($payment),
            'failure_url' => $this->compileFailReturnUrl($payment),
            'user_data' => $label,
        ];
        ksort($data, SORT_STRING);
        $dataStrings = [];
        foreach ($data as $key => $value) {
            $dataStrings[] = "$key=$value";
        }
        $dataString = implode('&', $dataStrings);
        $data['sign'] = md5("init_form{$dataString}{$paymentAccount->config['apiKey']}");

        try {
            $requestId = 'init';
            $request = $this->guzzle->get(self::API_INIT_FORM_URL, [
                'query' => $data,
                'timeout' => 6,
                'connect_timeout' => 6,
            ]);
            $responseData = json_decode($request->getBody()->getContents(), true);
            if (empty($responseData['url'])) {
                throw new MakeFormException("Request $requestId. No URL");
            }

            $requestId = 'cardData';
            $cardDataUrl = $responseData['url'];
            $params = [
                'json' => '1',
                'card_number' => $number,
                'card_holder' => $holder,
                'exp_date' => sprintf('%s/%s', $month, substr($year, 2)),
                'cvc' => $cvc,
                'doPayment' => '',
            ];
            $request = $this->guzzle->post($cardDataUrl, [
                'timeout' => 6,
                'connect_timeout' => 6,
                'form_params' => $params,
            ]);
            $responseData = json_decode($request->getBody()->getContents(), true);
            if (empty($responseData['paymentId'])) {
                throw new MakeFormException("Request $requestId. No paymentId");
            }

            $requestId = 'cvcForm';
            $mpayPaymentId = $responseData['paymentId'];
            //@TODO хрупко!
            $url = str_replace(
                'https://merchant.1payment.com/',
                "https://merchant.1payment.com/pp/$mpayPaymentId/",
                $cardDataUrl
            );
            $url .= "?checkLiveStatus=1&sign={$responseData['sign']}";

            for ($i = 0; $i < 3; $i++) {
                sleep(5);
                $formData = $this->cvcFormRequestMakeFormData($payment, $requestId, $mpayPaymentId, $url);
                if (null !== $formData) {
                    break;
                }
            }
            if (null === $formData) {
                throw new MakeFormException("Request $requestId. Cannot parse 3ds data");
            }

            return $formData;
        } catch (RequestException $e) {
            $requestCode = null;
            $response = $e->getResponse();
            if (null !== $response) {
                $responseCode = $response->getStatusCode();
                $responseContent = $response->getBody()->getContents();
            }

            throw new MakeFormException("Request $requestId. code: $responseCode, content: $responseContent");
        }
    }

    /**
     * @throws MakeFormException
     * @throws RequestException
     */
//    private function cvcFormRequestMakeFormData(
//        Payment $payment,
//        string $requestId,
//        string $mpayPaymentId,
//        string $url
//    ): ?FormData {
//        $request = $this->guzzle->get($url, ['timeout' => 6, 'connect_timeout' => 6]);
//        $responseContent = $request->getBody()->getContents();
//        libxml_use_internal_errors(true);
//        $dom = new DOMDocument();
//        if (false === $dom->loadHTML($responseContent)) {
//            throw new MakeFormException("Request $requestId. LoadHtml");
//        }
//        $tdsData = [/*'TermUrl' => $this->compileSuccessReturnUrl($payment)*/];
//        $tdsFieldNames = ['PaReq', 'MD', 'TermUrl'];
//        $inputFields = $dom->getElementsByTagName('input');
//        foreach ($inputFields as $inputField) {
//            $fieldName = $inputField->getAttribute('name');
//            if (in_array($fieldName, $tdsFieldNames, true)) {
//                $tdsData[$fieldName] = $inputField->getAttribute('value');
//            }
//        }
//        if (empty($tdsData[$tdsFieldNames[0]])) {
//            return null;
//        }
//
//        $form = $dom->getElementById($mpayPaymentId);
//        if (null === $form) {
//            throw new MakeFormException("Request $requestId. Form not found");
//        }
//
//        $formData = new FormData($form->getAttribute('action'), FormData::METHOD_POST, $tdsData);
//
//        return $formData;
//    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        $data = json_decode($request->getContent(), true);
        $paymentShotId = explode('-', $data['user_data'])[0];

        return $paymentShotId;
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = json_decode($request->getContent(), true);

        if (!empty($data['test'])) {
            throw new CheckingResultRequestException("test mode");
        }

        if ($data['status_description'] !== 'SUCCESS') {
            throw new SkipCheckingResultRequestException("Status '{$data['status_description']}'");
        }

        $inputAmount = $data['merchant_price'];
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $requestSign = $data['sign'];
        unset($data['sign']);
        ksort($data, SORT_STRING);
        $dataStrings = [];
        foreach ($data as $key => $value) {
            $dataStrings[] = "$key=$value";
        }
        $dataString = implode('&', $dataStrings);
        $sign = md5("{$dataString}{$paymentAccount->config['apiKey']}");
        if ($sign !== $requestSign) {
            throw new CheckingResultRequestException("Wrong sign");
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['status' => 0]);
    }

    /** @throws PayoutReceiverNotValidException */
    public function checkReceiver(string $receiver, int $accountId): void
    {
        $cardConstraint = new CardScheme(['schemes' => ['VISA', 'MASTERCARD', 'MIR']]);
        $errors = $this->validator->validate(
            $receiver,
            $cardConstraint
        );
        if (0 !== count($errors)) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_INCORRECT);
        }
    }

    public function getPayoutQueueName(): string
    {
        return MessageBroker::QUEUE_PAYOUT_MPAY_CARD_NAME;
    }

    public function getPayoutBalanceKey(): string
    {
        return self::BALANCE_KEY_DEFAULT;
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
            $params = [
                'amount' => $payout->amount,
                'destination' => $payoutSet->receiver,
                'partner_id' => $paymentAccount->config['partnerId'],
                'payout_type' => 'card',
                'project_id' => $paymentAccount->config['projectId'],
                'user_data' => $payout->id,
            ];
            $params['sign'] = md5('init_payout' . http_build_query($params) . $paymentAccount->config['projectKey']);
            $request = $this->guzzle->get('https://api.mpay.ru/init_payout', [
                'timeout' => 30,
                'connect_timeout' => 30,
                'query' => $params,
            ]);
            $content = $request->getBody()->getContents();
            $result = json_decode($content, true);
            if (isset($result['payout_id'])) {
                $payout->initData = ['payout_id' => $result['payout_id']];
                $this->repositoryProvider->get(Payout::class)->update($payout, ['initData']);
            } elseif (isset($result['error_code']) && $result['error_code'] === 9) {
                $this->paymentAccountant->dropBalance($paymentAccount);
                throw new CannotTransferException($content);
            } else {
                throw new CannotTransferException($content);

            }
        } catch (ConnectException $e) {
            throw new CannotTransferTimeoutException("{$e->getMessage()}", 0, $e);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotTransferException("Request code: $statusCode. Message: {$e->getMessage()}");
        }
    }

    /** @inheritdoc */
    public function checkPayout(array $initData, PaymentAccount $paymentAccount): int
    {
        try {
            $params = [
                'order_id' => $initData['payout_id'],
                'partner_id' => $paymentAccount->config['partnerId'],
            ];
            $params['sign'] = md5('status_payout' . http_build_query($params) . $paymentAccount->config['projectKey']);
            $request = $this->guzzle->get('https://api.mpay.ru/status_payout', [
                'timeout' => 10,
                'connect_timeout' => 10,
                'query' => $params,
            ]);
            $content = $request->getBody()->getContents();
            $result = json_decode($content, true);
            $this->logger->info('Check', $result);
            if (isset($result['status'])) {
                if ($result['status_description'] === 'SUCCESS') {
                    return 1;
                } elseif ($result['status_description'] === 'FAILURE') {
                    return -1;
                } else {
                    return 0;
                }
            }

            throw new CannotCheckException($content);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotCheckException("Request code: $statusCode. Message: {$e->getMessage()}");
        }
    }
}
