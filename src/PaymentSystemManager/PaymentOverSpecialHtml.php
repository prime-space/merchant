<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentAccount;
use App\Entity\Payment;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotCompileSpecialHtmlException;

interface PaymentOverSpecialHtml
{
    /** @throws CannotCompileSpecialHtmlException */
    public function getSpecialHtml(
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        Payment $payment,
        PaymentShot $paymentShot,
        string $description
    ): string;
}
