<?php namespace App\PaymentSystemManager;

use App\Entity\PaymentShot;

interface SpecialWaitingPage
{
    public function getWaitingPageData(PaymentShot $paymentShot): WaitingPageData;
}
