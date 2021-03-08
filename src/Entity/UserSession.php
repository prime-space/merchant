<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class UserSession
{
    /** @Db\IntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\VarcharType(length = 64) */
    public $crypt;
    /** @Db\VarcharType(length = 64) */
    public $token;
    /** @Db\TimestampType */
    public $lastActionTs;

    public static function create($userId, $crypt, $token): self
    {
        $item = new self();
        $item->userId = $userId;
        $item->crypt = $crypt;
        $item->token = $token;

        return $item;
    }
}

