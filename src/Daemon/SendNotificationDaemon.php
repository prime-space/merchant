<?php namespace App\Daemon;

use App\Entity\Notification;
use App\Entity\Payment;
use App\Entity\Shop;
use App\MessageBroker;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendNotificationDaemon extends Daemon
{
    /** 10 minutes delay between unsuccessful notifications */
    private const NOTIFICATION_DELAY_SEC = 600;

    private $messageBroker;
    private $guzzleClient;
    private $proxies;

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        GuzzleClient $guzzleClient,
        array $proxies
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->guzzleClient = $guzzleClient;
        $this->proxies = $proxies;
    }

    protected function do(InputInterface $input, OutputInterface $output)
    {
        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_NOTIFICATION_NAME);
        $this->logExtraDataKeeper->setParam('paymentId', $message['paymentId']);

        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        /** @var Payment $payment */
        $payment = $paymentRepository->findById($message['paymentId']);
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $data = [
            'systemPayment' => $payment->id,
            'payment' => $payment->payment,
            'shop' => $payment->shopId,
            'currency' => $payment->currency,
            'amount' => $payment->amount,
        ];
        foreach ($payment->userVars as $userVarKey => $userVarValue) {
            $data[$userVarKey] = $userVarValue;
        }
        ksort($data, SORT_STRING);
        $data['sign'] = hash('sha256', sprintf('%s:%s', implode(':', $data), $shop->secret));

        $proxies = $this->proxies;
        shuffle($proxies);
        $result = '';
        foreach ($proxies as $proxy) {
            $this->logger->info(sprintf('Over %s', parse_url($proxy, PHP_URL_HOST)));
            $options = [
                'timeout' => 6,
                'connect_timeout' => 6,
                'proxy' => $proxy,
                'form_params' => $data,
            ];
            try {
                $request = $this->guzzleClient->post($shop->resultUrl, $options);
                $code = $request->getStatusCode();
                $result = $request->getBody()->getContents();
                $statusId = Notification::STATUS_ID_SENT;
                $payment->notificationStatusId = Payment::NOTIFICATION_STATUS_ID_SENT;
                $paymentRepository->update($payment, ['notificationStatusId']);
                $this->logger->info('Success');
                break;
            } catch (RequestException $e) {
                $statusId = Notification::STATUS_ID_ERROR;
                $code = $e->getCode();
                $result = '';
                if ($code === 0) {
                    $this->logger->error('Cannot send', ['error' => $e->getMessage()]);
                    continue;
                } else {
                    $this->logger->error("Client error $code", ['error' => $e->getMessage()]);
                    $result = $e->getResponse()->getBody()->getContents();
                }
            }
        }
        $result = mb_substr($result, 0, 256, 'UTF-8');
        $result = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $result) ?? '{merchant_replace_error}';
        $notification = Notification::create($payment->id, $statusId, $data, $result, $code);
        $this->repositoryProvider->get(Notification::class)->create($notification);

        if ($notification->statusId === Notification::STATUS_ID_ERROR) {
            if ($message['try'] < 5) {
                $try = $message['try'] + 1;
                $this->messageBroker->createMessage(MessageBroker::QUEUE_NOTIFICATION_NAME, [
                    'paymentId' => $payment->id,
                    'try' => $try,
                ], self::NOTIFICATION_DELAY_SEC);
            } else {
                $payment->notificationStatusId = Payment::NOTIFICATION_STATUS_ID_ERROR;
                $paymentRepository->update($payment, ['notificationStatusId']);
            }
        }
    }
}
