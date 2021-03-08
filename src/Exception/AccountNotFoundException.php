<?php namespace App\Exception;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct()
    {
        parent::__construct('Account not found');
    }
}
