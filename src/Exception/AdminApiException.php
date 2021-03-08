<?php namespace App\Exception;

use Exception;

class AdminApiException extends Exception
{
    private $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        parent::__construct();
    }

    public function getData(): array
    {
        return $this->data;
    }
}
