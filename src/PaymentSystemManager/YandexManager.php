<?php namespace App\PaymentSystemManager;

use App\Constraints\PayoutReceiver;
use App\Entity\FastLog;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotCheckException;
use App\Exception\CannotCompileFormDataException;
use App\Exception\CannotInitPaymentException;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\CheckingResultRequestException;
use App\Exception\NotEnoughFundsException;
use App\Exception\PayoutReceiverNotValidException;
use App\Exception\YandexApiRequestException;
use App\FastDbLogger;
use App\MessageBroker;
use App\PaymentAccountant;
use App\TagServiceProvider\TagServiceInterface;
use DOMDocument;
use DOMElement;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use LogicException;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;

class YandexManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverForm,
    InputRequestResultInterface,
    PayoutInterface,
    WhiteBalancingInterface,
    PayoutWithChecking
{
    const API_URL = 'https://money.yandex.ru';

    const PAYMENT_METHOD_WALLET_ID = 14;
    const PAYMENT_METHOD_CARD_ID = 15;

    const RESULT_VALIDATION_CODEPRO = 'false';
    const RESULT_VALIDATION_UNACCEPTED = 'false';

    const CURRENCY_CODE_RUB = 643;

    const REQUEST_ERROR_AUTH_REJECT = 'authorization_reject';
    const REQUEST_ERROR_LIMIT_EXCEEDED = 'limit_exceeded';
    const REQUEST_ERROR_NOT_ENOUGH_FUNDS = 'not_enough_funds';
    const REQUEST_ERRORS_TO_DEACTIVATE_ACCOUNT = [
        self::REQUEST_ERROR_AUTH_REJECT,
        self::REQUEST_ERROR_LIMIT_EXCEEDED,
    ];

    private $guzzleClient;
    private $messageBroker;
    private $paymentAccountant;
    private $rabbitYandexProducer;
    private $fastDbLogger;
    private $repositoryProvider;

    public function __construct(
        Logger $logger,
        Router $router,
        GuzzleClient $guzzleClient,
        MessageBroker $messageBroker,
        PaymentAccountant $paymentAccountant,
        Producer $rabbitYandexProducer,
        FastDbLogger $fastDbLogger,
        RepositoryProvider $repositoryProvider
    ) {
        parent::__construct($logger, $router);
        $this->guzzleClient = $guzzleClient;
        $this->messageBroker = $messageBroker;
        $this->paymentAccountant = $paymentAccountant;
        $this->rabbitYandexProducer = $rabbitYandexProducer;
        $this->fastDbLogger = $fastDbLogger;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function getTagServiceName(): string
    {
        return 'yandex';
    }

    public function getPaymentSystemId(): int
    {
        return 1;
    }

    /** @throws CannotCompileFormDataException */
    public function mineCardNativeForm(
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        while (1) {
            try {
                $form = $this->getNativeYandexCardForm($payment, $paymentShot);

                break;
            } catch (CannotCompileFormDataException $e) {
                $this->initCardPayment($payment, $paymentAccount, $paymentShot, $description);
            }
            sleep(1);
        }

        $inputs = $form->getElementsByTagName('input');
        $customFields = ['skr_card-number', 'skr_year', 'skr_month', 'skr_cardCvc'];
        $fields = [];
        foreach ($inputs as $input) {
            $fieldName = $input->getAttribute('name');
            if ($input->getAttribute('type') === 'hidden' && !in_array($fieldName, $customFields, true)) {
                $fields[$fieldName] = $input->getAttribute('value');
            }
        }
        $formData = new FormData($form->getAttribute('action'), FormData::METHOD_POST, $fields, 'yandexCard');

        return $formData;
    }

    /** @inheritdoc */
    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        switch ($paymentMethod->id) {
            case self::PAYMENT_METHOD_CARD_ID:
                if (empty($paymentShot->initData)) {
                    $this->initCardPayment($payment, $paymentAccount, $paymentShot, $description);
                }
                $formData = new FormData(
                    $paymentShot->initData['url'],
                    FormData::METHOD_POST,
                    $paymentShot->initData['params']
                );

                break;
            case self::PAYMENT_METHOD_WALLET_ID:
                $formData = new FormData('https://money.yandex.ru/quickpay/confirm.xml', FormData::METHOD_POST, [
                    'receiver' => $paymentAccount->config['purse'],
                    'formcomment' => $description,
                    'short-dest' => $description,
                    'quickpay-form' => 'shop',
                    'paymentType' => 'PC',
                    'targets' => $description,
                    'sum' => $paymentShot->amount,
                    'label' => $paymentShot->id,
                    'successURL' => $this->compileSuccessReturnUrl($payment),
                ]);

                break;
            default:
                throw new LogicException("Unknown payment method #{$paymentMethod->id}");

        }

        return $formData;
    }

    /**
     * TODO RACE CONDITION
     * @throws CannotCompileFormDataException
     */
    private function initCardPayment(
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): void {
        try {
            $attempt = ($paymentShot->initData['attempt'] ?? 0) + 1;
            if ($attempt === 4) {
                $this->logger->error("Limit if init card payment reached {$paymentShot->paymentId}", [
                    'paymentShotId' => $paymentShot->id
                ]);

                throw new CannotInitPaymentException();
            }
            $this->logger->info("Init card payment {$paymentShot->paymentId} Attempt $attempt", [
                'paymentShotId' => $paymentShot->id
            ]);

            $requestId = $this->requestExternalPayment($paymentAccount, $paymentShot, $description);
            $processParams = $this->compileProcessParams($requestId, $paymentAccount, $payment);
            $externalPaymentData = $this->processExternalPayment($paymentShot, $processParams);
            $data = [
                'url' => $externalPaymentData['acs_uri'],
                'params' => $externalPaymentData['acs_params'],
                'attempt' => $attempt,
            ];
            $this->sendCheckMessage($payment, $paymentShot, $processParams, 30000);
            $paymentShot->initData = $data;
            $this->repositoryProvider->get(PaymentShot::class)->update($paymentShot, ['initData']);
            $this->logger->info("Init done", ['paymentShotId' => $paymentShot->id]);
        } catch (CannotInitPaymentException $e) {
            throw new CannotCompileFormDataException();
        }
    }

    /** @throws CannotCompileFormDataException */
    private function getNativeYandexCardForm(Payment $payment, PaymentShot $paymentShot): DOMElement
    {
        try {
            try {
                $request = $this->guzzleClient->get($paymentShot->initData['url'], [
                    'query' => $paymentShot->initData['params'],
                ]);
                $responseContent = $request->getBody()->getContents();
                $responseCode = $request->getStatusCode();
            } catch (RequestException $e) {
                $requestCode = null;
                $response = $e->getResponse();
                if (null !== $response) {
                    $responseCode = $response->getStatusCode();
                    $responseContent = $response->getBody()->getContents();
                }
                $this->logger->error('CannotCompileFormDataException Request', ['paymentId' => $payment->id]);

                throw new CannotCompileFormDataException();
            }
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            if (false === $dom->loadHTML($responseContent)) {
                $this->logger->error('CannotCompileFormDataException LoadHtml', ['paymentId' => $payment->id]);

                throw new CannotCompileFormDataException();
            }
            $forms = $dom->getElementsByTagName('form');
            if ($forms->length !== 1) {
                $this->logger->error(
                    "CannotCompileFormDataException Forms Count '{$forms->length}'",
                    ['paymentId' => $payment->id]
                );

                throw new CannotCompileFormDataException();
            }
        } catch (CannotCompileFormDataException $e) {
            $fastLogData = [
                'query' => $paymentShot->initData['params'],
                'responseCode' => $responseCode,
                'responseContent' => $responseContent,
            ];
            $this->fastDbLogger->log(FastLog::METHOD_YANDEX_GET_FORM, $payment->id, $fastLogData);

            throw $e;
        }

        return $forms->item(0);
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        return $request->request->getInt('label');
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = $request->request;

        $codepro = $data->get('codepro');
        if ($codepro !== self::RESULT_VALIDATION_CODEPRO) {
            throw new CheckingResultRequestException("Codepro is '$codepro', expect ".self::RESULT_VALIDATION_CODEPRO);
        }
        $unaccepted = $data->get('unaccepted');
        if ($unaccepted !== self::RESULT_VALIDATION_UNACCEPTED) {
            throw new CheckingResultRequestException(
                "Unaccepted is '$unaccepted', expect ".self::RESULT_VALIDATION_UNACCEPTED
            );
        }
        $currency = $data->getInt('currency');
        if ($currency !== self::CURRENCY_CODE_RUB) {
            throw new CheckingResultRequestException("Currency is '$currency', expect ".self::CURRENCY_CODE_RUB);
        }
        $amount = $data->get('withdraw_amount');
        if (1 === bccomp($paymentShot->amount, $amount, 2)) {
            throw new CheckingResultRequestException("Wrong amount '$amount', expect '{$paymentShot->amount}'");
        }

        $hash = hash('sha1', implode('&', [
            $data->get('notification_type'),
            $data->get('operation_id'),
            $data->get('amount'),
            $currency,
            $data->get('datetime'),
            $data->get('sender'),
            $codepro,
            $paymentAccount->config['secret'],
            $data->get('label'),
        ]));

        if ($hash !== $data->get('sha1_hash')) {
            throw new CheckingResultRequestException('Wrong hash');
        }
    }

    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string
    {
        return json_encode(['status' => 'ok']);
    }

    /** @inheritdoc */
    public function checkReceiver(string $receiver, int $accountId): void
    {
        if (preg_match('/^41001[0-9]{8,11}$/', $receiver) !== 1) {
            throw new PayoutReceiverNotValidException(PayoutReceiver::MESSAGE_KEY_INCORRECT);
        }
    }

    public function getPayoutQueueName(): string
    {
        return MessageBroker::QUEUE_PAYOUT_YANDEX_NAME;
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

        $label = (string) $payout->id;
        $amount = bcmul($payout->amount, 1, 2);
        $initData = $this->transfer($paymentAccount, $amount, $payoutSet->receiver, $description, $label);
        $payout->initData = $initData;
        $this->repositoryProvider->get(Payout::class)->update($payout, ['initData']);
    }

    public function getPayoutBalanceKey(): string
    {
        return self::BALANCE_KEY_DEFAULT;
    }

    public function compileProcessParams(string $requestId, PaymentAccount $paymentAccount, Payment $payment): array
    {
        $processParams = [
            'request_id' => $requestId,
            'instance_id' => $paymentAccount->config['instance_id'],
            'ext_auth_success_uri' => $this->compileSuccessReturnUrl($payment),
            'ext_auth_fail_uri' => $this->compileFailReturnUrl($payment),
        ];

        return $processParams;
    }

    public function sendCheckMessage(
        Payment $payment,
        PaymentShot $paymentShot,
        array $processParams,
        $delay,
        $isControlChecking = false
    ): void {
        $execTimestamp = time() + $delay / 1000;
        $message = [
            'paymentId' => $payment->id,
            'paymentShotId' => $paymentShot->id,
            'processParams' => $processParams,
            'timestamp' => time(),
            'isControlChecking' => $isControlChecking,
            'execTimestamp' => $execTimestamp,
        ];
        $this->rabbitYandexProducer->publish(json_encode($message), '', [], ['x-delay' => $delay]);
    }

    /** @throws CannotInitPaymentException */
    private function requestExternalPayment($paymentAccount, $paymentShot, $description): string
    {
        $url = sprintf('%s/api/request-external-payment', self::API_URL);
        try {
            $request = $this->guzzleClient->post($url, [
                'timeout' => 15,
                'connect_timeout' => 15,
                'verify' => false,
                'form_params' => [
                    'pattern_id' => 'p2p',
                    'to' => $paymentAccount->config['purse'],
                    'amount_due' => bcadd($paymentShot->amount, '0', 2),
                    'instance_id' => $paymentAccount->config['instance_id'],
                    'message' => $description,
                ],
            ]);
            $result = $request->getBody()->getContents();
            $result = json_decode($result, true);
            $status = $result['status'] ?? null;
            if ($status !== 'success') {
                $error = $result['error'] ?? '';

                throw new YandexApiRequestException($error);
            }

            return $result['request_id'];
        } catch (RequestException|YandexApiRequestException $e) {
            $this->logger->critical('Request external payment error', [
                'paymentShotId' => $paymentShot->id,
                'message' => $e->getMessage()
            ]);

            throw new CannotInitPaymentException('requestExternalPayment');
        }
    }

    /** @throws CannotInitPaymentException */
    private function processExternalPayment($paymentShot, $processParams): array
    {
        $url = sprintf('%s/api/process-external-payment', self::API_URL);
        try {
            $request = $this->guzzleClient->post($url, [
                'timeout' => 15,
                'connect_timeout' => 15,
                'verify' => false,
                'form_params' => $processParams,
            ]);
            $result = $request->getBody()->getContents();
            $result = json_decode($result, true);
            $acsParams = $result['acs_params'] ?? null;
            if ($acsParams === null || empty($acsParams['cps_context_id'])) {
                $error = $result['error'] ?? '';

                throw new YandexApiRequestException($error);
            }

            return $result;
        } catch (RequestException|YandexApiRequestException $e) {
            $this->logger->critical('Process external payment error', [
                'paymentShotId' => $paymentShot->id,
                'message' => $e->getMessage()
            ]);

            throw new CannotInitPaymentException('processExternalPayment');
        }
    }

    private function checkRequestErrorToDeactivateAccount(PaymentAccount $paymentAccount, string $error = null)
    {
        if (in_array($error, self::REQUEST_ERRORS_TO_DEACTIVATE_ACCOUNT, true)) {
            $this->paymentAccountant->deactivateAccount($paymentAccount, $error);
        }
    }

    public function isBalanceOverBalancingPoint(PaymentAccount $paymentAccount)
    {
        return bccomp($paymentAccount->balance[self::BALANCE_KEY_DEFAULT], self::BALANCING_POINT_AMOUNT, 2) >= 0;
    }

    /** @inheritdoc */
    public function transfer(
        PaymentAccount $paymentAccount,
        string $amount,
        string $receiver,
        string $description,
        string $label = null
    ): array {
        $url = sprintf('%s/api/request-payment', self::API_URL);
        try {
            $params = [
                'pattern_id' => 'p2p',
                'to' => $receiver,
                'amount_due' => $amount,
                'comment' => $description,
                'message' => $description,
            ];
            if (null !== $label) {
                $params['label'] = $label;
            }
            $request = $this->guzzleClient->post($url, [
                'timeout' => 15,
                'connect_timeout' => 15,
                'headers' => [
                    'Authorization' => "Bearer {$paymentAccount->config['token']}",
                ],
                'form_params' => $params,
            ]);
            $result = $request->getBody()->getContents();
            $result = json_decode($result, true);
            $status = $result['status'] ?? null;
            if ($status === 'success') {
                return ['request_id' => $result['request_id']];
            } else {
                $error = $result['error'] ?? '';
                $this->checkRequestErrorToDeactivateAccount($paymentAccount, $error);
                if ($error === self::REQUEST_ERROR_NOT_ENOUGH_FUNDS) {
                    $this->paymentAccountant->dropBalance($paymentAccount);
                }

                throw new CannotTransferException($error);
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
            $url = sprintf('%s/api/process-payment', self::API_URL);
            $request = $this->guzzleClient->post($url, [
                'timeout' => 15,
                'connect_timeout' => 15,
                'headers' => [
                    'Authorization' => "Bearer {$paymentAccount->config['token']}",
                ],
                'form_params' => [
                    'request_id' => $initData['request_id'],
                ],
            ]);
            $result = $request->getBody()->getContents();
            $result = json_decode($result, true);
            $this->logger->info('Check', $result);

            if (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    return 1;
                } elseif ($result['status'] === 'refused') {
                    $error = $result['error'] ?? '';
                    $this->logger->info('Refused', $result);
                    $this->checkRequestErrorToDeactivateAccount($paymentAccount, $error);
                    if ($error === self::REQUEST_ERROR_NOT_ENOUGH_FUNDS) {
                        $this->paymentAccountant->dropBalance($paymentAccount);
                        throw new NotEnoughFundsException();
                    }

                    return -1;
                } else {
                    return 0;
                }
            }

            throw new CannotCheckException($result);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $statusCode = null === $response ? null : $response->getStatusCode();

            throw new CannotCheckException("Request code: $statusCode. Message: {$e->getMessage()}");
        }
    }

    public function getAccountReceiver(PaymentAccount $paymentAccount): string
    {
        return $paymentAccount->config['purse'];
    }
}
