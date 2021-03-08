<?php namespace App\Entity;

use Ewll\DBBundle\Annotation as Db;
use Symfony\Component\Translation\TranslatorInterface;

class PayoutMethod
{
    /** @Db\IntType */
    public $id;
    /** @Db\TinyIntType */
    public $paymentSystemId;
    /** @Db\TinyIntType */
    public $currencyId;
    /** @Db\DecimalType */
    public $fee;
    /** @Db\VarcharType(length = 64) */
    public $name;
    /** @Db\VarcharType(length = 64) */
    public $code;
    /** @Db\DecimalType */
    public $chunkSize;
    /** @Db\BoolType */
    public $isEnabled;
    /** @Db\BoolType */
    public $defaultExcluded = 0;

    public function compileAdminApiView(PaymentSystem $paymentSystem, Currency $currency): array
    {
        $view = [
            'id' => $this->id,
            'paymentSystem' => $paymentSystem->name,
            'currency' => $currency->name,
            'name' => $this->name,
            'fee' => $this->fee,
            'code' => $this->code,
            'isEnabled' => $this->isEnabled,
            'isDefaultExcluded' => $this->defaultExcluded,
        ];

        return $view;
    }

    public function compileAdminApiUserPageView(
        bool $enabledPersonalFee,
        string $fee,
        bool $isEnabled
    ): array {
        $view = [
            'id' => $this->id,
            'name' => $this->name,
            'hasPersonalFee' => $enabledPersonalFee,
            'fee' => sprintf("%.2f", $fee),
            'isEnabled' => $isEnabled
        ];

        return $view;
    }

    public function compileVueSelectView(TranslatorInterface $translator): array
    {
        $view = [
            'value' => $this->code,
            'text' => $translator->trans("method.name.{$this->name}", [], 'payout'),
        ];

        return $view;
    }

    public function compileWalletView(TranslatorInterface $translator)
    {
        $view = [
            'fee' => $this->fee,
            'code' => $this->code,
            'name' => $translator->trans("method.name.{$this->name}", [], 'payout'),
        ];

        return $view;
    }
}
