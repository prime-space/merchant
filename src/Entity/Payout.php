<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Payout
{
    const STATUS_ID_UNKNOWN = 0;
    const STATUS_ID_QUEUE = 1;
    const STATUS_ID_PROCESS = 2;
    const STATUS_ID_SUCCESS = 3;
    const STATUS_ID_FAIL = 4;
    const STATUS_ID_CHECKING = 5;

    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $payoutSetId;
    /** @Db\IntType */
    public $paymentAccountId;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\DecimalType */
    public $credit;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\JsonType */
    public $initData = [];
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $payoutSetId,
        $amount,
        $credit
    ): self {
        $item = new self();
        $item->payoutSetId = $payoutSetId;
        $item->amount = $amount;
        $item->credit = $credit;

        return $item;
    }
}
