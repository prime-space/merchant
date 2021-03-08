<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\MessageBroker;
use App\TagServiceProvider\TagServiceInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

class TestManager extends AbstractPaymentSystemManager implements
    PaymentSystemManagerInterface,
    TagServiceInterface,
    PaymentOverForm
{
    private $messageBroker;

    public function __construct(
        Logger $logger,
        Router $router,
        MessageBroker $messageBroker
    ) {
        parent::__construct($logger, $router);
        $this->messageBroker = $messageBroker;
    }

    public function getTagServiceName(): string
    {
        return 'test';
    }

    public function getPaymentSystemId(): int
    {
        return 8;
    }

    public function getFormData(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): FormData {
        $formData = new FormData("/payment/{$payment->hash}", FormData::METHOD_POST, []);

        $this->messageBroker->createMessage(MessageBroker::QUEUE_EXEC_PAYMENT_NAME, [
            'paymentShotId' => $paymentShot->id
        ]);
        $this->logger->info("#{$payment->id} moved to execution by test method");

        return $formData;
    }
}
