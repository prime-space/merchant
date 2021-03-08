<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payout;
use App\Exception\CannotCheckException;
use App\Exception\NotEnoughFundsException;

interface PayoutWithChecking
{
    /**
     * @throws CannotCheckException
     * @throws NotEnoughFundsException
     */
    public function checkPayout(array $initData, PaymentAccount $paymentAccount): int;
}
