<?php namespace App\Exception;

use Exception;

class AdminApiDataValidationException extends Exception
{
    private $errors;

    public function __construct(array $errors = [])
    {
        $this->errors = $errors;
        parent::__construct();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
