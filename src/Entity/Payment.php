<?php namespace App\Entity;

use App\AdminApi;
use App\VueViewCompiler;
use Ewll\DBBundle\Annotation as Db;
use Symfony\Component\Translation\TranslatorInterface;

class Payment
{
    const STATUS_ID_NOT_EXECUTED = 1;
    const STATUS_ID_SUCCESS = 3;

    const NOTIFICATION_STATUS_ID_UNDEFINED = 0;
    const NOTIFICATION_STATUS_ID_SENDING = 1;
    const NOTIFICATION_STATUS_ID_SENT = 2;
    const NOTIFICATION_STATUS_ID_ERROR = 3;

    const NOTIFICATION_STATUSES_JS_CONFIG = [
        'undefined' => self::NOTIFICATION_STATUS_ID_UNDEFINED,
        'sending' => self::NOTIFICATION_STATUS_ID_SENDING,
        'sent' => self::NOTIFICATION_STATUS_ID_SENT,
        'error' => self::NOTIFICATION_STATUS_ID_ERROR,
    ];

    const REFUND_STATUS_ID_WAS_NOT = 1;
    const REFUND_STATUS_ID_VOUCHER_PROVIDED = 2;
    const REFUND_STATUS_ID_USED = 3;

    const PAYMENT_ID_REGEXP = '/\[\#(\d+)\]/';

    const CHART_INTERVAL_MONTHS = '12 MONTH';
    const CHART_INTERVAL_DAYS = '30 DAY';

    /** @Db\BigIntType */
    public $id;
    /** @Db\IntType */
    public $shopId;
    /** @Db\BigIntType */
    public $payment;
    /** @Db\VarcharType(length = 32) */
    public $sub_id = '';
    /** @Db\DecimalType */
    public $amount;
    /** @Db\BoolType */
    public $isFeeByClient;
    /** @Db\DecimalType */
    public $fee;
    /** @Db\DecimalType */
    public $credit;
    /** @Db\TinyIntType */
    public $currency;
    /** @Db\VarcharType(length = 256) */
    public $email;
    /** @Db\VarcharType(length = 64) */
    public $hash;
    /** @Db\VarcharType(length = 256) */
    public $successUrl;
    /** @Db\VarcharType(length = 256) */
    public $failUrl;
    /** @Db\IntType */
    public $paymentMethodId;
    /** @Db\VarcharType(length = 128) */
    public $description;
    /** @Db\JsonType */
    public $userVars = [];
    /** @Db\TinyIntType */
    public $notificationStatusId = self::NOTIFICATION_STATUS_ID_SENDING;
    /** @Db\TinyIntType */
    public $statusId = self::STATUS_ID_NOT_EXECUTED;
    /** @Db\VarcharType(length = 15) */
    public $ip;
    /** @Db\TinyIntType */
    public $refundStatusId = self::REFUND_STATUS_ID_WAS_NOT;
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $shopId,
        $payment,
        $sub_id,
        $amount,
        $isFeeByClient,
        $currency,
        $email,
        $hash,
        $successUrl,
        $failUrl,
        $description,
        $userVars,
        $ip
    ): self {
        $item = new self();
        $item->shopId = $shopId;
        $item->payment = $payment;
        $item->sub_id = $sub_id;
        $item->amount = $amount;
        $item->isFeeByClient = $isFeeByClient;
        $item->currency = $currency;
        $item->email = $email;
        $item->hash = $hash;
        $item->successUrl = $successUrl;
        $item->failUrl = $failUrl;
        $item->description = $description;
        $item->userVars = $userVars;
        $item->ip = $ip;

        return $item;
    }

    public function compileAdminApiFinderView(Currency $currency): array
    {
        $paymentStatus = $this->statusId === self::STATUS_ID_SUCCESS ? 'executed' : 'not executed';
        $view = [
            'id' => $this->id,
            'type' => 'Payment',
            'info' => sprintf("%s %s", $this->amount, $currency->name),
            'status' => $paymentStatus,
            'userPaymentId' => $this->payment,
            'date' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }

    public function compileAdminApiView(
        TranslatorInterface $translator,
        Currency $currency,
        Shop $shop,
        PaymentMethod $paymentMethod,
        array $notifications,
        User $user
    ): array {
        $notificationViews = [];
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $notificationViews[] = $notification->compileAdminApiView();
        }
        $view['payment'] = [
            'id' => $this->id,
            'userPaymentId' => $this->payment,
            'shop' => $shop->url,
            'shopId' => $shop->id,
            'amount' => $this->amount,
            'currency' => $currency->name,
            'email' => $this->email,
            'statusId' => $this->statusId,
            'status' => $this->statusId === self::STATUS_ID_SUCCESS ? 'Executed' : 'Not executed',
            'refundStatusId' => $this->refundStatusId,
            'refundStatusName' => $translator->trans("refund-status.{$this->refundStatusId}", [], 'payment'),
            'paymentMethod' => $paymentMethod->name,
            'description' => $this->description,
            'date' => $this->createdTs->format(AdminApi::DATE_FORMAT),
            'userId' => $user->id,
            'redirectFormData' => $this->compileRedirectFormData(),
        ];
        $view['notifications'] = $notificationViews;

        return $view;
    }

    public function compileAdminDetailView(
        TranslatorInterface $translator,
        PaymentMethod $paymentMethod,
        PaymentShot $paymentShot
    ): array {
        $feePayer = $this->isFeeByClient ? 'client' : 'shop';
        $view = [
            'id' => $this->id,
            'notificationStatusId' => $this->notificationStatusId,
            'methodName' => $paymentMethod->name,
            'createdDate' => $this->createdTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
            'amount' => $this->appendCurrencySign($translator, $this->amount),
            'credit' => $this->credit ? $this->appendCurrencySign($translator, $this->credit) : '',
            'paysFee' => $translator->trans("fee-payer-{$feePayer}", [], 'payment'),
            'fee' => $this->fee ? $this->appendCurrencySign($translator, $this->fee) : '',
            'notificationStatus' => $translator->trans(
                "notification-status.{$this->notificationStatusId}",
                [],
                'payment'
            ),
            'email' => $this->email,
            'description' => $this->description,
            'successDate' => $paymentShot->successTs->format(VueViewCompiler::TIMEZONEJS_DATE_FORMAT),
        ];

        return $view;
    }

    private function appendCurrencySign(TranslatorInterface $translator, string $moneySum)
    {
        $stringWithSign = sprintf(
            '%s %s',
            $moneySum,
            $translator->trans("currency.{$this->currency}.sign", [], 'payment')
        );

        return $stringWithSign;
    }

    public function paymentPageView()
    {
        $view = [
            'id' => $this->id,
            'paymentMethodId' => $this->paymentMethodId,
            'amount' => $this->amount,
            'description' => $this->description,
        ];

        return $view;
    }

    public function compileAdminApiListView()
    {
        $view = [
            'id' => $this->id,
            'description' => $this->description,
            'amount' => $this->amount,
            'created' => $this->createdTs->format(AdminApi::DATE_FORMAT),
            'actions' => [
                ['icon' => 'description', 'type' => 'entity', 'entity' => 'payment', 'entityId' => $this->id],
            ]
        ];

        return $view;
    }

    public function compileRedirectFormData()
    {
        $fields = [
            'shop' => $this->shopId,
            'payment' => $this->payment,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
        foreach ($this->userVars as $userVarKey => $userVarValue) {
            $fields[$userVarKey] = $userVarValue;
        }

        return [
            'method' => 'POST',
            'action' => $this->successUrl,
            'fields' => $fields,
        ];
    }
}
