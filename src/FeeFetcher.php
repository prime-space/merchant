<?php namespace App;

use App\Entity\PaymentMethod;
use App\Entity\PayoutMethod;
use App\Entity\Shop;
use App\Entity\User;
use Ewll\DBBundle\Repository\RepositoryProvider;

class FeeFetcher
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function fetchPayoutFee(PayoutMethod $payoutMethod, User $user): string
    {
        return $user->personalPayoutFees[$payoutMethod->id] ?? $payoutMethod->fee;
    }

    public function hasPersonalPayoutFee(PayoutMethod $payoutMethod, User $user): string
    {
        return isset($user->personalPayoutFees[$payoutMethod->id]);
    }

    public function fetchPaymentFee(PaymentMethod $paymentMethod, Shop $shop): string
    {
        return $shop->personalPaymentFees[$paymentMethod->id] ?? $paymentMethod->fee;
    }

    public function hasPersonalPaymentFee(PaymentMethod $paymentMethod, Shop $shop): string
    {
        return isset($shop->personalPaymentFees[$paymentMethod->id]);
    }

    public function calcFeeAmount(string $amount, string $fee): string
    {
        return bcmul(bcdiv($fee, 100, 4), $amount, 2);
    }
}
