<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Currency
{
    const ID_RUB = 3;
    const NAME_RUB = 'rub';
    const NAME_BTC = 'btc';
    const CURRENCY_RUB_ID = 3;

    const MAX_SCALE = 8;

    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 32) */
    public $name;
    /** @Db\DecimalType */
    public $rate;
    /** @Db\TinyIntType */
    public $scale;
}
