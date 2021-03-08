<?php namespace App\Logger;

class GlobalLogProcessor
{
    private $logExtraDataKeeper;

    public function __construct(LogExtraDataKeeper $logExtraDataKeeper)
    {
        $this->logExtraDataKeeper = $logExtraDataKeeper;
    }

    public function processRecord(array $record)
    {
        $data = $this->logExtraDataKeeper->getData();
        foreach ($data as $key => $value) {
            $record['extra'][$key] = $value;
        }

        return $record;
    }
}
