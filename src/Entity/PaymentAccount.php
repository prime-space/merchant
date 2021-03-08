<?php namespace App\Entity;

use App\PaymentAccountFetcher;
use Ewll\DBBundle\Annotation as Db;

class PaymentAccount
{
    const DEFAULT_VALUE_BALANCE = [];

    /** @Db\IntType */
    public $id;
    /** @Db\TinyIntType */
    public $paymentSystemId;
    /** @Db\VarcharType(length = 64) */
    public $name;
    /** @Db\CipheredType */
    public $config;
    /** @Db\TinyIntType */
    public $weight;
    /** @Db\SetType */
    public $enabled;
    /** @Db\JsonType */
    public $balance = self::DEFAULT_VALUE_BALANCE;
    /** @Db\JsonType */
    public $assignedIds = [];
    /** @Db\BoolType */
    public $isWhite;
    /** @Db\BoolType */
    public $isActive = true;

    public static function create(
        $paymentSystemId,
        $name,
        $config,
        $weight,
        $enabled,
        $assignedIds,
        $isWhite,
        $isActive
    ): self {
        $item = new self();
        $item->paymentSystemId = (int)$paymentSystemId;
        $item->name = $name;
        $item->config = $config;
        $item->weight = $weight;
        $item->enabled = $enabled;
        $item->assignedIds = $assignedIds;
        $item->isWhite = $isWhite;
        $item->isActive = $isActive;

        return $item;
    }

    public static function getFakeForTestMethod()
    {
        $item = new self();
        $item->id = 0;
        $item->paymentSystemId = PaymentSystem::TEST_ID;
        $item->name = 'test';
        $item->config = [];
        $item->weight = 1;
        $item->enabled = [PaymentAccountFetcher::ENABLED_FOR_MERCHANT];
        $item->assignedIds = [];
        $item->isWhite = false;
        $item->isActive = true;

        return $item;
    }

    public function getBalance(string $item = null): string
    {
        if (null === $item) {
            $balance = count($this->balance) > 0 ? reset($this->balance) : '0';
        } else {
            $balance = $this->balance[$item] ?? '0';
        }

        return $balance;
    }

    public function dropBalance(): void
    {
        $this->balance = self::DEFAULT_VALUE_BALANCE;
    }

    public function setBalanceItem($balance, $item = null): void
    {
        $key = $item ?? 0;
        $this->balance[$key] = $balance;
    }

    public function compileLogView($balanceKey): array
    {
        return [
            'id' => $this->id,
            'balance' => $this->balance[$balanceKey],
        ];
    }
}
