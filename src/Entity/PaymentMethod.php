<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;

class PaymentMethod
{
    const METHOD_WMR_ID = 1;
    const METHOD_WMZ_ID = 2;
    const METHOD_WME_ID = 3;
    const METHOD_WMU_ID = 4;
    const METHOD_QIWI_ID = 5;
    const METHOD_YANDEX_ID = 14;
    const METHOD_YANDEX_CARD_ID = 15;
    const METHOD_TEST_ID = 19;
    const METHOD_BITCOIN_ID = 20;
    const METHOD_MPAY_BEELINE_ID = 21;
    const METHOD_EXCHANGER_ID = 26;
    const METHOD_MPAY_CARD_ID = 37;

    const METHOD_NAME_CARD_RUB_DIR = 'card_rub_dir';

    /** @Db\IntType */
    public $id;
    /** @Db\TinyIntType */
    public $paymentSystemId;
    /** @Db\TinyIntType */
    public $currencyId;
    /** @Db\TinyIntType */
    public $currencyViewId;
    /** @Db\TinyIntType */
    public $groupId;
    /** @Db\DecimalType */
    public $fee;
    /** @Db\DecimalType */
    public $minimumAmount;
    /** @Db\VarcharType(length = 64) */
    public $name;
    /** @Db\VarcharType(length = 64) */
    public $code;
    /** @Db\VarcharType(length = 64) */
    public $externalCode;
    /** @Db\VarcharType(length = 64) */
    public $img;
    /** @Db\TinyIntType */
    public $position;
    /** @Db\IntType */
    public $alternativeId;
    /** @Db\BoolType */
    public $enabled;
    /** @Db\BoolType */
    public $isDefaultExcluded = 0;

    public $paymentAccounts = [];

    public function compileAdminApiShopPageView(
        $dayStat,
        bool $enabled,
        bool $isEnabledByUser,
        bool $hasPersonalFee,
        string $fee
    ): array {
        $view = [
            'id' => $this->id,
            'name' => $this->name,
            'isEnabled' => $enabled,
            'isEnabledByUser' => $isEnabledByUser,
            'hasPersonalFee' => $hasPersonalFee,
            'fee' => sprintf("%.2f", $fee),
            'dayStat' => $dayStat,
        ];

        return $view;
    }

    public function compileAdminApiView(PaymentSystem $paymentSystem, Currency $currency): array
    {
        $view = [
            'id' => $this->id,
            'name' => $this->name,
            'paymentSystem' => $paymentSystem->name,
            'currency' => $currency->name,
            'fee' => $this->fee,
            'isEnabled' => $this->enabled,
        ];

        return $view;
    }

    public function compileShopPaymentMethodsView(): array
    {
        $view = [
            'name' => $this->name,
            'via' => $this->code,
        ];

        return $view;
    }
}
