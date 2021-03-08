<?php namespace App\Entity;

use App\AdminApi;
use App\FeeFetcher;
use Ewll\DBBundle\Annotation as Db;
use Symfony\Component\Translation\TranslatorInterface;

class Shop
{
    const STATUS_ID_NEW = 1;
    const STATUS_ID_OK = 2;
    const STATUS_ID_ON_VERIFICATION = 3;
    const STATUS_ID_DECLINED = 4;
    const CONSIDERATION_STATUSES = [
        self::STATUS_ID_OK,
        self::STATUS_ID_DECLINED,
        self::STATUS_ID_ON_VERIFICATION,
    ];

    /** @Db\IntType */
    public $id;
    /** @Db\IntType */
    public $userId;
    /** @Db\IntType */
    public $maskedId = Masked::ID_DEFAULT;
    /** @Db\VarcharType(length = 64) */
    public $name;
    /** @Db\VarcharType(length = 128) */
    public $url;
    /** @Db\VarcharType(length = 256) */
    public $description;
    /** @Db\VarcharType(length = 64) */
    public $secret;
    /** @Db\DecimalType */
    public $paymentDayLimit = '100000';
    /** @Db\VarcharType(length = 128) */
    public $successUrl;
    /** @Db\VarcharType(length = 128) */
    public $failUrl;
    /** @Db\VarcharType(length = 128) */
    public $resultUrl;
    /** @Db\BoolType */
    public $isPostbackEnabled = false;
    /** @Db\VarcharType(length = 512) */
    public $postbackUrl = '';
    /** @Db\BoolType */
    public $isTestMode;
    /** @Db\BoolType */
    public $isFeeByClient;
    /** @Db\BoolType */
    public $isAllowedToRedefineUrl;
    /** @Db\TinyIntType */
    public $statusId = 1;
    /** @Db\JsonType */
    public $excludedMethodsByUser = [];
    /** @Db\JsonType */
    public $excludedMethodsByAdmin = [];
    /** @Db\JsonType */
    public $personalPaymentFees = [];
    /** @Db\TimestampType */
    public $createdTs;

    public static function create(
        $userId,
        $name,
        $url,
        $description,
        $secret,
        $successUrl,
        $failUrl,
        $resultUrl,
        $isTestMode,
        $isFeeByClient,
        $isAllowedToRedefineUrl,
        $excludedMethodsByAdmin
    ): self {
        $item = new self();
        $item->userId = $userId;
        $item->name = $name;
        $item->url = $url;
        $item->description = $description;
        $item->secret = $secret;
        $item->successUrl = $successUrl;
        $item->failUrl = $failUrl;
        $item->resultUrl = $resultUrl;
        $item->isTestMode = $isTestMode;
        $item->isFeeByClient = $isFeeByClient;
        $item->isAllowedToRedefineUrl = $isAllowedToRedefineUrl;
        $item->excludedMethodsByAdmin = $excludedMethodsByAdmin;

        return $item;
    }

    public function compileAdminApiView(TranslatorInterface $translator = null): array
    {
        $view = [
            'id' => $this->id,
            'userId' => $this->userId,
            'name' => $this->name,
            'url' => $this->url,
            'domain' => parse_url($this->url, PHP_URL_HOST),
            'scheme' => parse_url($this->url, PHP_URL_SCHEME),
            'description' => $this->description,
            'successUrl' => $this->successUrl,
            'failUrl' => $this->failUrl,
            'resultUrl' => $this->resultUrl,
            'isTestMode' => $this->isTestMode,
            'isFeeByClient' => $this->isFeeByClient,
            'isAllowedToRedefineUrl' => $this->isAllowedToRedefineUrl,
            'statusId' => $this->statusId,
            'statusName' => $translator !== null
                ? $translator->trans("shop.admin.api.status.{$this->statusId}", [], 'admin')
                : $this->statusId,
            'excludedMethodsByUser' => $this->excludedMethodsByUser,
            'excludedMethodsByAdmin' => $this->excludedMethodsByAdmin,
            'personalPaymentFees' => $this->personalPaymentFees,
            'createdTs' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }

    public function compileAdminApiFinderView(TranslatorInterface $translator): array
    {
        $view = [
            'id' => $this->id,
            'type' => 'Shop',
            'info' => $this->url,
            'status' => $translator->trans("shop.admin.api.status.{$this->statusId}", [], 'admin'),
            'date' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }

    public function compileAdminApiPageView(
        array $paymentMethods,
        array $paymentMethodsDayStat,
        TranslatorInterface $translator,
        FeeFetcher $feeFetcher,
        PaymentDayStatistic $paymentDayStatistic,
        string $currencyName
    ): array {
        $paymentMethodViews = [];
        /** @var PaymentMethod $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            $isEnabledByAdmin = !in_array($paymentMethod->id, $this->excludedMethodsByAdmin, true);
            $isEnabledByUser = !in_array($paymentMethod->id, $this->excludedMethodsByUser, true);
            $hasPersonalFee = $feeFetcher->hasPersonalPaymentFee($paymentMethod, $this);
            $fee = $feeFetcher->fetchPaymentFee($paymentMethod, $this);
            $paymentMethodDayStat = $paymentMethodsDayStat[$paymentMethod->id] ?? null;
            $paymentMethodViews[] = $paymentMethod->compileAdminApiShopPageView(
                $paymentMethodDayStat,
                $isEnabledByAdmin,
                $isEnabledByUser,
                $hasPersonalFee,
                $fee
            );
        }
        $shopView = $this->compileAdminApiView($translator);
        $shopView['paymentDayLimit'] = $this->paymentDayLimit;
        $shopView['paymentDayAmount'] = $paymentDayStatistic->amount;
        $shopView['paymentDayStatisticCurrency'] = $currencyName;
        $view = [
            'shop' => $shopView,
            'paymentMethods' => $paymentMethodViews,
        ];

        return $view;
    }

    public function compileAdminView(
        TranslatorInterface $translator,
        PaymentDayStatistic $paymentDayStatistic,
        int $currencyId
    ) {
        $dailyPaymentAmount = $paymentDayStatistic->amount;
        $view = [
            'id' => $this->id,
            'userId' => $this->userId,
            'name' => $this->name,
            'url' => $this->url,
            'description' => $this->description,
            'secret' => '',
            'successUrl' => $this->successUrl,
            'failUrl' => $this->failUrl,
            'resultUrl' => $this->resultUrl,
            'isPostbackEnabled' => $this->isPostbackEnabled,
            'postbackUrl' => $this->postbackUrl,
            'isTestMode' => $this->isTestMode,
            'isFeeByClient' => $this->isFeeByClient,
            'isAllowedToRedefineUrl' => $this->isAllowedToRedefineUrl,
            'statusId' => $this->statusId,
            'statusName' => $translator->trans("shop.status.{$this->statusId}", [], 'admin'),
            'excludedMethodsByUser' => $this->excludedMethodsByUser,
            'excludedMethodsByAdmin' => $this->excludedMethodsByAdmin,
            'personalPaymentFees' => $this->personalPaymentFees,
            'dailyAmount' => $dailyPaymentAmount,
            'dailyLimit' => $this->paymentDayLimit,
            'dailyStatisticCurrency' => $translator->trans("currency.{$currencyId}.short", [], 'payment'),
            'createdTs' => $this->createdTs->format(AdminApi::DATE_FORMAT),
        ];

        return $view;
    }

    public function changeDomainSchemeAllUrls(string $domain, string $scheme)
    {
        $this->url = $this->changeDomainScheme($this->url, $domain, $scheme);
        $this->resultUrl = $this->changeDomainScheme($this->resultUrl, $domain, $scheme);
        $this->successUrl = $this->changeDomainScheme($this->successUrl, $domain, $scheme);
        $this->failUrl = $this->changeDomainScheme($this->failUrl, $domain, $scheme);
    }

    private function changeDomainScheme(string $url, string $domain, string $scheme)
    {
        $parsedUrl = parse_url($url);
        $parsedUrl['scheme'] = $scheme;
        $parsedUrl['host'] = $domain;
        $scheme = $parsedUrl['scheme'] . '://';
        $host = $parsedUrl['host'];
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return sprintf("%s%s%s%s%s%s%s%s", $scheme, $user, $pass, $host, $port, $path, $query, $fragment);
    }
}
