<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentShot;
use App\Exception\CheckingResultRequestException;
use App\Exception\SkipCheckingResultRequestException;
use Symfony\Component\HttpFoundation\Request;

interface InputRequestResultInterface
{
    public function getPaymentShotIdFromResultRequest(Request $request): int;
    /**
     * @throws CheckingResultRequestException
     * @throws SkipCheckingResultRequestException
     */
    public function checkResultRequest(
        Request $request,
        Payment $payment,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot
    ): void;
    public function getInputResultRequestSuccessMessage(PaymentShot $paymentShot): string;
}
