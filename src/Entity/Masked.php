<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Masked
{
    const ID_DEFAULT = 1;

    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $name;
    /** @Db\VarcharType(length = 64) */
    public $domain;
    /** @Db\VarcharType(length = 64) */
    public $key;
}
