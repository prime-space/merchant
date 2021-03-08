<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;

interface WhiteBalancingInterface
{
    const BALANCING_POINT_AMOUNT = '1000';
    const BALANCING_KEEP_AMOUNT = '50';

    public function isBalanceOverBalancingPoint(PaymentAccount $paymentAccount);
    /**
     * @throws CannotTransferException
     * @throws CannotTransferTimeoutException
     */
    public function transfer(
        PaymentAccount $paymentAccount,
        string $amount,
        string $receiver,
        string $description,
        string $label = null
    ): array;
    public function getAccountReceiver(PaymentAccount $paymentAccount): string;
    public function getPayoutBalanceKey(): string;
}
