<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payout;
use App\Entity\PayoutSet;
use App\Exception\CannotTransferException;
use App\Exception\CannotTransferTimeoutException;
use App\Exception\PayoutReceiverNotValidException;

//@TODO extend to payout extension
interface PayoutInterface
{
    public function getPaymentSystemId(): int;
    /** @throws PayoutReceiverNotValidException */
    public function checkReceiver(string $receiver, int $accountId): void;
    public function getPayoutQueueName(): string;
    public function getPayoutBalanceKey(): string;
    /**
     * @throws CannotTransferException
     * @throws CannotTransferTimeoutException
     */
    public function payout(
        Payout $payout,
        PayoutSet $payoutSet,
        string $description,
        PaymentAccount $paymentAccount = null
    ): void;
}
