<?php namespace App\Consumer;

use App\Logger\LogExtraDataKeeper;
use App\MessageBroker;
use App\PaymentSystemManager\YandexManager;
use Ewll\DBBundle\Repository\RepositoryProvider;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Symfony\Bridge\Monolog\Logger;

class YandexTransactionConsumer implements ConsumerInterface
{
    const TRANSACTION_LIFETIME_SEC = 86400;//1 day
    const ON_REQUEST_EXCEPTION_DELAY_SEC = 30;

    private $logger;
    private $guzzleClient;
    private $yandexManager;
    private $logExtraDataKeeper;
    private $repositoryProvider;
    private $messageBroker;
    private $rabbitYandexProducer;

    public function __construct(
        Logger $logger,
        GuzzleClient $guzzleClient,
        YandexManager $yandexManager,
        LogExtraDataKeeper $logExtraDataKeeper,
        RepositoryProvider $repositoryProvider,
        MessageBroker $messageBroker,
        Producer $rabbitYandexProducer
    ) {
        $this->logger = $logger;
        $this->guzzleClient = $guzzleClient;
        $this->yandexManager = $yandexManager;
        $this->logExtraDataKeeper = $logExtraDataKeeper;
        $this->repositoryProvider = $repositoryProvider;
        $this->messageBroker = $messageBroker;
        $this->rabbitYandexProducer = $rabbitYandexProducer;
    }

    /** @inheritdoc */
    public function execute(AMQPMessage $msg)
    {
        $message = json_decode($msg->body, true);

        $this->logExtraDataKeeper->setData([
            'iterationKey' => uniqid(),
            'name' => 'YandexTransactionConsumer',
            'paymentId' => $message['paymentId'],
        ]);

        try {
            $lag = time() - $message['execTimestamp'];
            $isControlChecking = isset($message['isControlChecking']) && true === $message['isControlChecking'];
            $isControlCheckingLog = $isControlChecking ? 'isControlChecking' : '';
            $this->logger->info("Checking $isControlCheckingLog. Lag: {$lag}s");

            $url = sprintf('%s/api/process-external-payment', YandexManager::API_URL);
            try {
                $request = $this->guzzleClient->post($url, [
                    'timeout' => 6,
                    'connect_timeout' => 6,
                    'form_params' => $message['processParams'],
                ]);
                $result = $request->getBody()->getContents();
                $result = json_decode($result, true);

                if ($result['status'] === 'success') {
                    $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
                        'paymentShotId' => $message['paymentShotId']
                    ]);
                    $this->logger->info("Success, moved to execution. $isControlCheckingLog");
                } else {
                    $paymentOnCheckingTimeSec = time() - $message['timestamp'];
                    if ($isControlChecking) {
                        $this->logger->info("Not success, $isControlCheckingLog");

                        return;
                    } elseif ($paymentOnCheckingTimeSec >= self::TRANSACTION_LIFETIME_SEC) {
                        $this->logger->info('Not success, expired');

                        return;
                    } else {
                        $delay = $this->getDelaySec($paymentOnCheckingTimeSec);
                        $this->logger->info("Not success, returned to queue with delay $delay ms.");
                        $this->returnToQueue($message, $delay);
                    }
                }
            } catch (RequestException $e) {
                $this->returnToQueue($message, self::ON_REQUEST_EXCEPTION_DELAY_SEC);
                $this->logger->critical('Request error, returned to queue');
            }

            $this->repositoryProvider->clear();
        } catch (Exception $e) {
            $this->logger->critical('Shutdown daemon: '.$e->getMessage());
            throw new RuntimeException();
        }
    }

    private function getDelaySec(int $paymentOnCheckingTimeSec): int
    {
        $intervals = [
            [
                'min' => 0,
                'max' => 180,
                'delay' => 15000,
            ],
            [
                'min' => 180,
                'max' => 500,
                'delay' => 30000,
            ],
            [
                'min' => 500,
                'max' => 800,
                'delay' => 45000,
            ],
            [
                'min' => 600,
                'max' => 1800,
                'delay' => 60000,
            ],
            [
                'min' => 1800,
                'max' => 5400,
                'delay' => 600000,
            ],
            [
                'min' => 5400,
                'max' => self::TRANSACTION_LIFETIME_SEC,
                'delay' => 3600000,
            ],
        ];
        foreach ($intervals as $interval) {
            if ($this->isInInterval($paymentOnCheckingTimeSec, $interval['min'], $interval['max'])) {
                return $interval['delay'];
            }
        }

        throw new RuntimeException('Unknown time difference');
    }

    private function isInInterval(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value < $max;
    }

    private function returnToQueue(array $message, int $delay): void
    {
        $message['execTimestamp'] = time() + $delay / 1000;
        $this->rabbitYandexProducer->publish(json_encode($message), '', [], ['x-delay' => $delay]);
    }
}

