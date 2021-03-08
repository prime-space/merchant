<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Session
{
    const CLEAR_SESSIONS_INTERVAL_HR = 8760;
    const COOKIE_DURATION_SEC = 31536000;

    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $key;
    /** @Db\JsonType */
    public $params;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create($key, $params): self
    {
        $item = new self();
        $item->key = $key;
        $item->params = $params;

        return $item;
    }
}

