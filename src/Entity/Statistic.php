<?php namespace App\Entity;

use DateTime;
use Ewll\DBBundle\Annotation as Db;

class Statistic
{
    const METHOD_PAYMENT = 'payment';

    /** @Db\BigIntType */
    public $id;
    /** @Db\VarcharType(length = 32) */
    public $method;
    /** @Db\BigIntType */
    public $methodId;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\DateType */
    public $createdDate;

    public static function create(
        $method,
        $methodId,
        $amount
    ): self {
        $item = new self();
        $item->method = $method;
        $item->methodId = $methodId;
        $item->amount = $amount;
        $item->createdDate = new DateTime();

        return $item;
    }
}
