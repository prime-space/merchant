<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class FastLog
{
    const METHOD_YANDEX_GET_FORM = 'yandexGetForm';

    /** @Db\BigIntType */
    public $id;
    /** @Db\VarcharType(length = 32) */
    public $method;
    /** @Db\BigIntType */
    public $methodId;
    /** @Db\JsonType */
    public $data;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $method,
        $methodId,
        $data
    ): self {
        $item = new self();
        $item->method = $method;
        $item->methodId = $methodId;
        $item->data = $data;

        return $item;
    }
}
