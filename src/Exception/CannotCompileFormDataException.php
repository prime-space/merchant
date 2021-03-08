<?php namespace App\Exception;

use Exception;

class CannotCompileFormDataException extends Exception
{
    private $context;

    public function __construct(string $message = '', array $context = [])
    {
        $this->context = $context;

        parent::__construct($message);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
