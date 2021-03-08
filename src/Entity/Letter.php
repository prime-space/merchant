<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Letter
{
    const STATUS_ID_NEW = 1;
    const STATUS_ID_SENT = 3;
    const STATUS_ID_ERROR = 4;

    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\VarcharType(length = 64) */
    public $email;
    /** @Db\VarcharType(length = 256) */
    public $subject;
    /** @Db\TextType */
    public $body;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $userId,
        $email,
        $subject,
        $body
    ): self {
        $item = new self();
        $item->userId = $userId;
        $item->email = $email;
        $item->subject = $subject;
        $item->body = $body;

        return $item;
    }
}
