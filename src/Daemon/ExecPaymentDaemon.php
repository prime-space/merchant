<?php namespace App\Daemon;

use App\Accountant;
use App\Entity\PaymentMethod;
use App\Entity\Statistic;
use App\PaymentDayStatisticCounter;
use App\Entity\Payment;
use App\Entity\PaymentShot;
use App\Entity\Shop;
use App\MessageBroker;
use App\PostbackManager;
use DateTime;
use Exception;
use Ewll\DBBundle\DB\Client as DbClient;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExecPaymentDaemon extends Daemon
{
    private $messageBroker;
    private $defaultDbClient;
    private $accountant;
    private $paymentDayStatisticCounter;
    private $postbackManager;

    protected function configure()
    {
        $this->setName('daemon:exec-payment');
    }

    public function __construct(
        Logger $logger,
        MessageBroker $messageBroker,
        DbClient $defaultDbClient,
        Accountant $accountant,
        PaymentDayStatisticCounter $paymentDayStatisticCounter,
        PostbackManager $postbackManager
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->messageBroker = $messageBroker;
        $this->defaultDbClient = $defaultDbClient;
        $this->accountant = $accountant;
        $this->paymentDayStatisticCounter = $paymentDayStatisticCounter;
        $this->postbackManager = $postbackManager;
    }

    /** @inheritdoc */
    protected function do(InputInterface $input, OutputInterface $output)
    {
        bcscale(2);

        $message = $this->messageBroker->getMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME);
        $this->logger->info("Handle paymentShot #{$message['paymentShotId']}");

        $paymentShotRepository = $this->repositoryProvider->get(PaymentShot::class);
        /** @var PaymentShot $paymentShot */
        $paymentShot = $paymentShotRepository->findById($message['paymentShotId']);
        $paymentShot->statusId = PaymentShot::STATUS_ID_SUCCESS;
        $paymentShot->successTs = new DateTime();

        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        /** @var Payment $payment */
        $payment = $paymentRepository->findById($paymentShot->paymentId);
        $this->logger->info("Payment #{$payment->id}");
        if ($payment->statusId === Payment::STATUS_ID_SUCCESS) {
            $this->logger->info('Already executed');

            return;
        }

        /** @var Shop $shop */
        $shop = $this->repositoryProvider->get(Shop::class)->findById($payment->shopId);

        if ($paymentShot->paymentMethodId === PaymentMethod::METHOD_TEST_ID && !$shop->isTestMode) {
            $this->logger->error('Test method, but shop is not in test mode');

            return;
        }

        $paymentDayStatistic = $this->paymentDayStatisticCounter->getPaymentDayStatisticByShopIdForToday($shop->id);

        $this->defaultDbClient->beginTransaction();
        try {
            $payment->fee = $paymentShot->fee;
            $payment->credit = $payment->isFeeByClient ? $payment->amount : bcsub($payment->amount, $paymentShot->fee);
            $payment->paymentMethodId = $paymentShot->paymentMethodId;
            $payment->statusId = Payment::STATUS_ID_SUCCESS;
            $paymentShotRepository->update($paymentShot);
            $paymentRepository->update($payment);

            if ($paymentShot->paymentMethodId !== PaymentMethod::METHOD_TEST_ID) {
                $this->accountant->increase(
                    $shop->userId,
                    Accountant::METHOD_PAYMENT,
                    $payment->id,
                    $payment->credit,
                    $payment->currency
                );
                $this->paymentDayStatisticCounter->increase($paymentDayStatistic, $payment->amount, $payment->currency);

                //@TODO Конвертация из валюты платежа!!!
                if (bccomp($payment->fee, '0', 2) === 1) {
                    $statistic = Statistic::create(Statistic::METHOD_PAYMENT, $payment->id, $payment->fee);
                    $this->repositoryProvider->get(Statistic::class)->create($statistic);
                }
            }

            $this->defaultDbClient->commit();

            if ($this->postbackManager->isNeedToSend($payment)) {
                $this->postbackManager->putToQueue($payment, PostbackManager::EVENT_PAYMENT_PAID);
            }

            $this->messageBroker->createMessage(MessageBroker::QUEUE_NOTIFICATION_NAME, [
                'paymentId' => $payment->id,
                'try' => 1,
            ]);
        } catch (Exception $e) {
            $this->defaultDbClient->rollback();
            throw $e;
        }
    }
}
