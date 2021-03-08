<?php namespace App\Exception;

use Exception;

class PaymentShotNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('PaymentShot not found');
    }
}
