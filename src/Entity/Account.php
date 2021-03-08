<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class Account
{
    const NO_LAST_TRANSACTION_ID = 0;

    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\IntType */
    public $currencyId;
    /** @Db\DecimalType */
    public $balance = '0';
    /** @Db\BigIntType */
    public $lastTransactionId = self::NO_LAST_TRANSACTION_ID;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $userId,
        $currencyId
    ): self {
        $item = new self();
        $item->userId = $userId;
        $item->currencyId = $currencyId;

        return $item;
    }

    public function compileAdminApiView(Currency $currency): array
    {
        $view = [
            'id' => $this->id,
            'balance' => $this->balance,
            'currency' => $currency->name,
        ];

        return $view;
    }
}
