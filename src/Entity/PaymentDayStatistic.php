<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;
use \DateTime;

class PaymentDayStatistic
{
    /** @Db\IntType */
    public $id;
    /** @Db\IntType */
    public $shopId;
    /** @Db\BoolType */
    public $isLimitExceededEmailSent = false;
    /** @Db\DecimalType */
    public $amount = 0;
    /** @Db\TimestampType */
    public $date;

    public static function create(
        $shopId,
        $date
    ): self {
        $item = new self();
        $item->shopId = $shopId;
        $item->date = $date;

        return $item;
    }
}
