<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class PaymentSystem
{
    const QIWI_ID = 3;
    const TEST_ID = 8;
    const BITCOIN_ID = 10;
    const GAMEMONEY_ID = 14;

    /** @Db\TinyIntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $name;
}
