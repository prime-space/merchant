<?php namespace App\PaymentSystemManager;

use Symfony\Bridge\Monolog\Logger;

interface PaymentSystemManagerInterface
{
    const BALANCE_KEY_DEFAULT = 0;

    public function getPaymentSystemId(): int;
    public function isNeedToWaitingPage(): bool;
    public function getLogger(): Logger;
}
