<?php namespace App\Exception;

use Exception;

class CannotFetchPaymentAccountException extends Exception
{
    public function __construct()
    {
        parent::__construct('Cannot fetch payment account.');
    }
}
