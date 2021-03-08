<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Voucher
{
    const METHOD_NAME_REFUND = 'refund';

    const STATUS_ID_NEW = 1;
    const STATUS_ID_PROCESS = 2;
    const STATUS_ID_USED = 3;

    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $key;
    /** @Db\VarcharType(length = 32) */
    public $method;
    /** @Db\BigIntType */
    public $methodId;
    /** @Db\IntType */
    public $currencyId;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\IntType */
    public $userId;
    /** @Db\TimestampType */
    public $usedTs;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $key,
        $method,
        $methodId,
        $currencyId,
        $amount
    ): self {
        $item = new self();
        $item->key = $key;
        $item->method = $method;
        $item->methodId = $methodId;
        $item->currencyId = $currencyId;
        $item->amount = $amount;

        return $item;
    }
}
