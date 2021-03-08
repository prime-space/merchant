<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class PaymentShot
{
    const STATUS_ID_WAITING = 1;
    const STATUS_ID_SUCCESS = 2;

    /** @Db\BigIntType */
    public $id;
    /** @Db\BigIntType */
    public $paymentId;
    /** @Db\IntType */
    public $paymentMethodId;
    /** @Db\IntType */
    public $paymentAccountId;
    /** @Db\IntType */
    public $subPaymentAccountId;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\DecimalType */
    public $amount;
    /** @Db\DecimalType */
    public $fee;
    /** @Db\JsonType */
    public $initData = [];
    /** @Db\TimestampType */
    public $createdTs;
    /** @Db\TimestampType */
    public $successTs;

    public static function create(
        $paymentId,
        $paymentMethodId,
        $paymentAccountId,
        $amount,
        $fee
    ): self {
        $item = new self();
        $item->paymentId = $paymentId;
        $item->paymentMethodId = $paymentMethodId;
        $item->paymentAccountId = $paymentAccountId;
        $item->amount = $amount;
        $item->fee = $fee;

        return $item;
    }

    public function compileAdminApiPaymentPageView(PaymentMethod $paymentMethod)
    {
        $view = [
            'id' => $this->id,
            'name' => $paymentMethod->name,
        ];

        return $view;
    }
}
