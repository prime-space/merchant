<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class SystemAddBalance
{
    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $accountId;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\VarcharType(length = 128) */
    public $comment;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $accountId,
        $amount,
        $comment
    ): self {
        $item = new self();
        $item->accountId = $accountId;
        $item->amount = $amount;
        $item->comment = $comment;

        return $item;
    }
}
