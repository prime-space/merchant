<?php namespace App\Exception;

use Exception;

class CannotSendEmailException extends Exception
{
    private $data;

    public function __construct(string $message, array $data = [])
    {
        $this->data = $data;
        parent::__construct($message);
    }

    public function getData(): array
    {
        return $this->data;
    }
}
