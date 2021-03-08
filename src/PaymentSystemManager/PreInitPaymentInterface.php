<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotInitPaymentException;

interface PreInitPaymentInterface
{
    /** @throws CannotInitPaymentException */
    public function preInitPayment(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): array;
}
