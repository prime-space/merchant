<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentMethod;
use App\Entity\PaymentShot;
use App\Exception\CannotBuildLinkUrlException;

interface PaymentOverLink
{
    /** @throws CannotBuildLinkUrlException */
    public function getLinkUrl(
        Payment $payment,
        PaymentMethod $paymentMethod,
        PaymentAccount $paymentAccount,
        PaymentShot $paymentShot,
        string $description
    ): string;
}
