<?php namespace App;

use App\Entity\Currency;
use App\Entity\PaymentDayStatistic;
use App\Entity\Shop;
use App\Exception\CannotSetLimitExceededEmailSentFlag;
use App\Repository\PaymentDayStatisticRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use \DateTime;

class PaymentDayStatisticCounter
{
    const DATE_FORMAT = 'Y-m-d';

    private $currencyConverter;
    private $repositoryProvider;

    public function __construct(CurrencyConverter $currencyConverter, RepositoryProvider $repositoryProvider)
    {
        $this->currencyConverter = $currencyConverter;
        $this->repositoryProvider = $repositoryProvider;
    }

    public function increase(
        PaymentDayStatistic $paymentDayStatistic,
        string $amount,
        int $currencyId
    ): void {
        $currencyRubId = Currency::CURRENCY_RUB_ID;
        $amountConvertedRub = $this->currencyConverter->convert($currencyId, $currencyRubId, $amount);
        /** @var PaymentDayStatisticRepository $paymentDayStatisticRepository */
        $paymentDayStatisticRepository = $this->repositoryProvider->get(PaymentDayStatistic::class);
        $paymentDayStatisticRepository->createOnDuplicateIncreaseAmount($paymentDayStatistic, $amountConvertedRub);
    }

    public function isDailyLimitExceeded(
        PaymentDayStatistic $paymentDayStatistic,
        Shop $shop,
        string $amount,
        int $currencyId
    ): bool {
        $isUnlimited = bccomp($shop->paymentDayLimit, '0', 2) === 0;
        if ($isUnlimited) {
            return false;
        }
        $currencyRubId = Currency::CURRENCY_RUB_ID;
        $amountConvertedRub = $this->currencyConverter->convert($currencyId, $currencyRubId, $amount);
        $sum = bcadd($paymentDayStatistic->amount, $amountConvertedRub, 2);
        $isDailyLimitExceeded = !(bccomp($shop->paymentDayLimit, $sum, 2) === 1);

        return $isDailyLimitExceeded;
    }

    public function getPaymentDayStatisticByShopIdForToday(int $shopId): PaymentDayStatistic
    {
        /** @var PaymentDayStatisticRepository $paymentDayStatisticRepository */
        $paymentDayStatisticRepository = $this->repositoryProvider->get(PaymentDayStatistic::class);
        $today = date(self::DATE_FORMAT);
        /** @var PaymentDayStatistic|null $paymentDayStatistic */
        $paymentDayStatistic = $paymentDayStatisticRepository->findOneBy(['shopId' => $shopId, 'date' => $today]);
        if ($paymentDayStatistic === null) {
            $nowDateTime = new DateTime();
            $paymentDayStatistic = PaymentDayStatistic::create($shopId, $nowDateTime);
        }

        return $paymentDayStatistic;
    }

    public function getPaymentDayStatisticsIndexedByShopIdForToday(array $shopIds): array
    {
        /** @var PaymentDayStatisticRepository $paymentDayStatisticRepository */
        $paymentDayStatisticRepository = $this->repositoryProvider->get(PaymentDayStatistic::class);
        /** @var PaymentDayStatistic[] $paymentDayStatistics */
        $paymentDayStatisticsIndexedByShopId = $paymentDayStatisticRepository
            ->findByShopIdsCurrentDayIndexedByShopId($shopIds);
        foreach ($shopIds as $shopId) {
            if (!isset($paymentDayStatisticsIndexedByShopId[$shopId])) {
                $nowDateTime = new DateTime();
                $paymentDayStatisticsIndexedByShopId[$shopId] = PaymentDayStatistic::create($shopId, $nowDateTime);
            }
        }

        return $paymentDayStatisticsIndexedByShopId;
    }

    /** @throws CannotSetLimitExceededEmailSentFlag */
    public function setLimitExceededEmailSentFlag(
        PaymentDayStatistic $paymentDayStatistic
    ): void {
        /** @var PaymentDayStatisticRepository $paymentDayStatisticRepository */
        $paymentDayStatisticRepository = $this->repositoryProvider->get(PaymentDayStatistic::class);
        $affectedRows = $paymentDayStatisticRepository
            ->getAffectedRowsCreateOrUpdateIsLimitExceededEmailSent($paymentDayStatistic);
        if ($affectedRows == 0) {
            throw new CannotSetLimitExceededEmailSentFlag();
        }
    }
}
