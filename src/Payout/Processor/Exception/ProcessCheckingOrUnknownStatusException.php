<?php namespace App\Payout\Processor\Exception;

use Exception;

class ProcessCheckingOrUnknownStatusException extends Exception
{
    const CODE_PAYMENT_ACCOUNT_ID_IS_NULL = 1;
    const CODE_PAYMENT_SYSTEM_MANAGER_NOT_FOUND = 2;
    const CODE_PAYOUT_STATUS_MISMATCH = 3;
    const CODE_PAYMENT_SYSTEM_MANAGER_WITHOUT_CHECKING = 4;
    const CODE_CANNOT_CHECK = 5;
    const CODE_NOT_ENOUGH_FUNDS = 6;

    public function __construct(int $code, string $message = '')
    {
        parent::__construct($message, $code);
    }
}
