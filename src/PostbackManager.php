<?php namespace App;

use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\Shop;
use Ewll\DBBundle\Repository\RepositoryProvider;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Symfony\Bridge\Monolog\Logger;

class PostbackManager
{
    const EVENT_PAYMENT_CREATED = 'lead';
    const EVENT_PAYMENT_PAID = 'sale';

    private $repositoryProvider;
    private $rabbitPostbackProducer;
    private $logger;
    private $guzzleClient;

    public function __construct(
        RepositoryProvider $repositoryProvider,
        Logger $logger,
        GuzzleClient $guzzleClient,
        Producer $rabbitPostbackProducer
    ) {
        $this->repositoryProvider = $repositoryProvider;
        $this->rabbitPostbackProducer = $rabbitPostbackProducer;
        $this->logger = $logger;
        $this->guzzleClient = $guzzleClient;
    }

    public function isNeedToSend(Payment $payment)
    {
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        $isSubIdNotEmpty = !empty($payment->sub_id);
        $isPostbackEnabled = $shop->isPostbackEnabled;

        $isNeedToSend = $isSubIdNotEmpty && $isPostbackEnabled;

        return $isNeedToSend;
    }

    public function putToQueue(Payment $payment, string $event)
    {
        $message = [
            'paymentId' => $payment->id,
            'event' => $event,
        ];
        $this->rabbitPostbackProducer->publish(json_encode($message), '', [], ['x-delay' => 0]);
    }

    public function send(int $paymentId, string $event)
    {
        $this->logger->info("Sending event '$event''");
        /** @var Payment $payment */
        $payment = $this->repositoryProvider->get(Payment::class)->findById($paymentId);
        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        if (null !== $payment->paymentMethodId) {
            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $this->repositoryProvider->get(PaymentMethod::class)->findById($payment->paymentMethodId);
            $method = $paymentMethod->code;
        } else {
            $method = null;
        }

        if (!$shop->isPostbackEnabled) {
            $this->logger->info("Postback disabled");

            return;
        }

        $vars = [
            'sub1' => $payment->sub_id,
            'status' => $event,
            'sum' => $payment->amount,
            'profit' => $payment->credit,
            'time' => time(),
            'method' => $method,
        ];
        $url = $shop->postbackUrl;
        foreach ($vars as $key => $value) {
            $placeholder = '{'.$key.'}';
            $url = str_replace($placeholder, $value, $url);
        }

        try {
            $this->logger->info("Url: $url");
            $this->guzzleClient->get($url, [
                'timeout' => 6,
                'connect_timeout' => 6,
            ]);

            $this->logger->info("Success");
        } catch (RequestException $e) {
            $this->logger->critical("Request error: {$e->getMessage()}");
        }
    }
}
