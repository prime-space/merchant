<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class PaymentMethodGroup
{
    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $key;
    /** @Db\VarcharType(length = 64) */
    public $img;
}
