<?php namespace App\PaymentSystemManager;

use App\Entity\Payment;
use App\Entity\PaymentAccount;
use App\Entity\PaymentShot;
use App\Exception\SelfFormHandlingException;

interface PaymentOverSelfForm
{
    const FORM_TYPE_MOBILE = 'mobile';
    const FORM_TYPE_CARD = 'card';

    public function getSelfFormType(): string;
    /** @throws SelfFormHandlingException */
    public function handleSelfForm(
        Payment $payment,
        PaymentShot $paymentShot,
        PaymentAccount $paymentAccount,
        array $requestData,
        string $description
    ): ?FormData;
}
