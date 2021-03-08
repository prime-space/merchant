<?php namespace App\PaymentSystemManager;

use App\Controller\MerchantController;
use App\Entity\Payment;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Routing\Router;

abstract class AbstractPaymentSystemManager
{
    public $logger;
    protected $router;

    public function __construct(Logger $logger, Router $router)
    {
        $this->logger = $logger;
        $this->router = $router;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function isNeedToWaitingPage(): bool
    {
        return false;
    }

    public function compileSuccessReturnUrl(Payment $payment): string
    {
        $url = $this->router->generate(
            MerchantController::PAYMENT_BY_HASH_ROUTE,
            ['hash' => $payment->hash],
            Router::ABSOLUTE_URL
        );

        return $url;
    }

    public function compileFailReturnUrl(Payment $payment): string
    {
        $url = $this->router->generate(
            MerchantController::PAYMENT_BY_HASH_ERROR_ROUTE,
            ['hash' => $payment->hash],
            Router::ABSOLUTE_URL
        );

        return $url;
    }
}
