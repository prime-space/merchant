<?php namespace App\PaymentSystemManager;

class WaitingPageData
{
    const TYPE_BITCOIN_ADDRESS = 'bitcoinAddress';
    const TYPE_BITCOIN_CONFIRMATIONS = 'bitcoinConfirmations';
    const TYPE_MOBILE = 'mobile';
    const TYPE_COMMON = 'common';

    private $type;
    private $data = [];

    public function __construct(string $type, array $data = [])
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
