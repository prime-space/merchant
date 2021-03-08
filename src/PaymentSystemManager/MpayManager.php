<?php namespace App\PaymentSystemManager;

use App\Constraints\PayoutReceiver;
use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentShot;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotCheckException;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\CheckingResultRequestException;
use App\Exception\PayoutReceiverNotValidException;
use App\Exception\SelfFormHandlingException;
use App\Exception\SkipCheckingResultRequestException;
use App\MessageBroker;
use App\PaymentAccountant;
use App\TagServiceProvider\TagServiceInterface;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use LogicException;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MpayManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverSelfForm,
    SpecialWaitingPage,
    InputRequestResultInterface,
    PayoutInterface,
    PayoutWithChecking
{
    const API_MC_URL = 'http://mc.mpay.ru';
    const INIT_STATUS_OK = '0';

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
        return 'mpay';
    }

    public function getPaymentSystemId(): int
    {
        return 11;
    }

    public function getSelfFormType(): string
    {
        return PaymentOverSelfForm::FORM_TYPE_MOBILE;
    }

    /** @inheritdoc */
    public function handleSelfForm(
        Payment $payment,
        PaymentShot $paymentShot,
        PaymentAccount $paymentAccount,
        array $requestData,
        string $description
    ): ?FormData {
        if (!isset($requestData['number']) || preg_match('/^79\d{9}$/', $requestData['number']) !== 1) {
            throw new LogicException('Incorrect input data');
        }
        $number = $requestData['number'];
        $successMessage = $this->translator->trans('thank-you', [], 'payment');
        try {
            $params = [
                'username' => $paymentAccount->config['username'],
                'service_id' => $paymentAccount->config['projectId'],
                'description' => $description,
                'price' => bcadd($paymentShot->amount, 0, 2),
                'success_message' => $successMessage,
                'phone' => $number,
                'merchant_order_id' => $paymentShot->id,
            ];
            $params['sign'] = md5(sprintf(
                '%s%s%s%s',
                $number,
                $paymentAccount->config['projectId'],
                $paymentAccount->config['username'],
                $paymentAccount->config['apiKey']
            ));
            $request = $this->guzzle->post(self::API_MC_URL, [
                'body' => json_encode($params),
                'timeout' => 6,
                'connect_timeout' => 6,
            ]);
            $result = json_decode($request->getBody()->getContents(), true);
            $status = $result['status'] ?? null;
            if ($status === self::INIT_STATUS_OK) {
                $this->logger->info("Success init payment", [
                    'paymentId' => $paymentShot->paymentId,
                    'phone' => $number,
                    'status' => $status,
                    'orderId' => $result['order_id'],
                ]);
            } else {
                $this->logger->error(
                    "Cannot init payment",
                    ['paymentId' => $paymentShot->paymentId, 'phone' => $number, 'status' => $status]
                );

                throw new SelfFormHandlingException();
            }
            $paymentShot->initData = [
                'number' => $number,
                'order_id' => $result['order_id'],
            ];
            $this->repositoryProvider->get(PaymentShot::class)->update($paymentShot);

            return null;
        } catch (RequestException $e) {
            throw new SelfFormHandlingException();
        }
    }

    public function getWaitingPageData(PaymentShot $paymentShot): WaitingPageData
    {
        if (isset($paymentShot->initData['number'])) {
            $type = WaitingPageData::TYPE_MOBILE;
            $data = ['number' => $paymentShot->initData['number'],];
        } else {
            //TODO return to form page??
            $type = WaitingPageData::TYPE_COMMON;
            $data = [];
        }

        $waitingPageData = new WaitingPageData($type, $data);

        return $waitingPageData;
    }

    public function getPaymentShotIdFromResultRequest(Request $request): int
    {
        $data = json_decode($request->getContent(), true);

        return $data['merchant_order_id'];
    }

    /** @inheritdoc */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void {
        $data = json_decode($request->getContent(), true);

        if ($data['order_status'] === 'failure') {
            throw new SkipCheckingResultRequestException("Status 'failure'");
        }

        /*sometimes changed?????
        if ($data['order_id'] !== $paymentShot->initData['order_id']) {
            throw new CheckingResultRequestException("order_id does not match");
        }*/

        if ($data['phone'] !== $paymentShot->initData['number']) {
            throw new CheckingResultRequestException("number does not match");
        }
        if ($data['order_status'] !== 'success') {
            throw new CheckingResultRequestException(
                "order_status haven't value 'success' ('{$data['order_status']}'')"
            );
        }

        $ips = [
            '80.77.174.2',
            '80.77.174.3',
            '80.77.174.4',
            '80.77.174.5',
            '80.77.174.6',
            '80.77.174.225',
            '80.77.174.226',
            '80.77.174.227',
            '80.77.174.228',
            '80.77.174.229',
            '80.77.174.230',
        ];
        $ip = $request->server->get('HTTP_X_FORWARDED_FOR');
        if (!in_array($ip, $ips, true)) {
            throw new CheckingResultRequestException("IP '$ip' does not allow");
        }

        $inputAmount = $data['merchant_price'];
        if (1 === bccomp($paymentShot->amount, $inputAmount, 2)) {
            throw new CheckingResultRequestException("Wrong amount. Payment: {$payment->amount}, Input: $inputAmount");
        }

        $sign = md5(sprintf(
            '%s%s%s%s%s',
            $data['phone'],
            $data['order_status'],
            $paymentAccount->config['projectId'],
            $paymentAccount->config['username'],
            $paymentAccount->config['apiKey']
        ));
        if ($sign !== $data['sign']) {
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
        return MessageBroker::QUEUE_PAYOUT_MPAY_NAME;
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
