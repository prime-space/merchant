<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Transaction
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\IntType */
    public $accountId;
    /** @Db\VarcharType(length = 32) */
    public $method;
    /** @Db\BigIntType */
    public $methodId;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\IntType */
    public $currencyId;
    /** @Db\DecimalType */
    public $balance;
    /** @Db\BigIntType */
    public $accountOperationId;
    /** @Db\TimestampType */
    public $executingTs;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $userId,
        $accountId,
        $method,
        $methodId,
        $amount,
        $currencyId,
        $executingTs = null
    ): self {
        $item = new self();
        $item->userId = $userId;
        $item->accountId = $accountId;
        $item->method = $method;
        $item->methodId = $methodId;
        $item->amount = $amount;
        $item->currencyId = $currencyId;
        $item->executingTs = $executingTs;

        return $item;
    }

    public function isExecuted()
    {
        return $this->accountOperationId !== null;
    }
}
