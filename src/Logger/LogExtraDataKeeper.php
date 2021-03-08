<?php namespace App\Logger;

class LogExtraDataKeeper
{
    private $data = [];

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setParam($key, $value): void
    {
        $this->data[$key] = $value;
    }
}
