<?php namespace App\Exception;

use Exception;

class PaymentShotNotExpectedToExecException extends Exception
{
    public function __construct(int $paymentShotId)
    {
        parent::__construct("PaymentShot #$paymentShotId not expected to execution");
    }
}
