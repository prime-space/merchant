<?php namespace App\Entity;

use App\Authenticator;
use App\FeeFetcher;
use Ewll\DBBundle\Annotation as Db;
use DateTimeZone;
use DateTime;

class User
{
    const LK_MODE_MERCHANT = 'merchant';
    const LK_MODE_PURSE = 'purse';

    /** @Db\IntType */
    public $id;
    /** @Db\VarcharType(length = 64) */
    public $email;
    /** @Db\VarcharType(length = 64) */
    public $pass;
    /** @Db\VarcharType(length = 16) */
    public $lkMode;
    /** @Db\VarcharType(30) */
    public $timezone = 'Atlantic/Reykjavik';
    /** @Db\JsonType */
    public $apiIps = [];
    /** @Db\JsonType */
    public $personalPayoutFees = [];
    /** @Db\VarcharType(length = 64) */
    public $apiSecret = '';
    /** @Db\VarcharType(length = 64) */
    public $emailConfirmationCode = null;
    /** @Db\BoolType */
    public $isApiEnabled = 0;
    /** @Db\BoolType */
    public $isBlocked = 0;
    /** @Db\BoolType */
    public $isEmailConfirmed = 0;
    /** @Db\TimestampType */
    public $doNotAskPassUntilTs;
    /** @Db\JsonType */
    public $excludedPayoutMethods = [];
    /** @Db\TimestampType */
    public $createdTs;

    public $token;

    public static function create($email, $pass, $lkMode, $excludedPayoutMethodIds): self
    {
        $item = new self();
        $item->email = $email;
        $item->pass = $pass;
        $item->lkMode = $lkMode;
        $item->excludedPayoutMethods = $excludedPayoutMethodIds;

        return $item;
    }

    public function compileJsConfigView(Authenticator $authenticator): array
    {
        $view = [
            'timezone' => $this->timezone,
            'apiIps' => $this->apiIps,
            'isApiEnabled' => $this->isApiEnabled,
            'isBlocked' => $this->isBlocked,
            'doNotAskPass' => $authenticator->doNotAskPass(),
        ];

        return $view;
    }

    public function compileAdminApiView(
        array $shops,
        array $accounts,
        array $currencies,
        array $payoutMethods,
        FeeFetcher $feeFetcher
    ): array {
        $shopViews = [];
        $accountViews = [];
        $payoutMethodViews = [];
        /** @var Shop $shop */
        foreach ($shops as $shop) {
            $shopViews[] = $shop->compileAdminApiView();
        }
        /** @var Account $account */
        foreach ($accounts as $account) {
            $currency = $currencies[$account->currencyId];
            $accountViews[] = $account->compileAdminApiView($currency);
        }
        /** @var PayoutMethod $payoutMethod */
        foreach ($payoutMethods as $payoutMethod) {
            $hasPersonalFee = $feeFetcher->hasPersonalPayoutFee($payoutMethod, $this);
            $fee = $feeFetcher->fetchPayoutFee($payoutMethod, $this);
            $isPayoutMethodEnabledForUser = !in_array($payoutMethod->id, $this->excludedPayoutMethods);
            $payoutMethodViews[] = $payoutMethod->compileAdminApiUserPageView(
                $hasPersonalFee,
                $fee,
                $isPayoutMethodEnabledForUser
            );
        }
        $targetTimeZone = new DateTimeZone($this->timezone);
        $dateTime = new DateTime('now', $targetTimeZone);
        $timezoneOffset = $dateTime->format('P');
        $userView = [
            'id' => $this->id,
            'email' => $this->email,
            'timezone' => $timezoneOffset,
            'apiIps' => $this->apiIps,
            'isApiEnabled' => $this->isApiEnabled,
            'isBlocked' => $this->isBlocked,
        ];
        $view = [
            'shops' => $shopViews,
            'accounts' => $accountViews,
            'payoutMethods' => $payoutMethodViews,
            'user' => $userView,
        ];

        return $view;
    }

    public function compileAdminApiFinderView(): array
    {
        $view = [
            'id' => $this->id,
            'type' => 'User',
            'info' => $this->email,
            'status' => $this->isBlocked ? 'blocked' : 'active',
            'isBlocked' => $this->isBlocked,
            'date' => '',
        ];

        return $view;
    }
}
