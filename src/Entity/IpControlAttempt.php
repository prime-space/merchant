<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class IpControlAttempt
{
    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 15) */
    public $ip;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($ip): self
    {
        $item = new self();
        $item->ip = $ip;

        return $item;
    }
}

