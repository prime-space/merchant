<?php namespace App;

use App\Entity\Currency;
use App\Entity\Payment;
use App\Entity\Shop;
use App\Entity\User;
use App\Exception\NotFoundException;
use App\Repository\PaymentRepository;
use Ewll\DBBundle\Repository\RepositoryProvider;
use RuntimeException;
use DateTime;
use DateTimeZone;

class ChartDataCompiler
{
    const DAY_FORMAT = 'd-m-Y';
    const MONTH_FORMAT = 'm-Y';

    const INTERVAL_MONTH = 'month';
    const INTERVAL_DAY = 'day';

    const INTERVAL_MONTH_LENGTH = 11;
    const INTERVAL_DAY_LENGTH = 29;

    private $currencyConverter;
    private $repositoryProvider;

    public function __construct(CurrencyConverter $currencyConverter, RepositoryProvider $repositoryProvider)
    {
        $this->currencyConverter = $currencyConverter;
        $this->repositoryProvider = $repositoryProvider;
    }

    /** @throws  NotFoundException */
    public function compilePaymentChartData(User $user, $shopId = null): array
    {
        $queryParams = [];
        $userTimezone = new DateTimeZone($user->timezone);
        $userTime = new DateTime('now', $userTimezone);
        $userTimezoneOffset = $userTime->format('P');
        $queryParams['userId'] = $user->id;
        if ($shopId !== null) {
            $queryParams['id'] = $shopId;
        }
        $lastTwelveMonthsDataRange = $this->generateDataRangeWithZerosFormatted(
            ChartDataCompiler::INTERVAL_MONTH,
            $userTimezone
        );
        $lastThirtyDaysDataRange = $this->generateDataRangeWithZerosFormatted(
            ChartDataCompiler::INTERVAL_DAY,
            $userTimezone
        );
        /** @var Shop[] $shops */
        $shops = $this->repositoryProvider->get(Shop::class)->findBy($queryParams);
        if ($shopId !== null && count($shops) === 0) {
            throw new NotFoundException();
        }
        if (count($shops) === 0) {
            $emptyChartData = $this->compileView(
                [],
                $lastTwelveMonthsDataRange,
                [],
                $lastThirtyDaysDataRange,
                ['total', 'success', 'amount']
            );

            return $emptyChartData;
        }
        $shopIds = array_column($shops, 'id');
        /** @var PaymentRepository $paymentRepository */
        $paymentRepository = $this->repositoryProvider->get(Payment::class);
        //@TODO cache
        $resultByMonths = $paymentRepository->findByShopIdForChartByMonthsOrDaysWithOffset(
            $shopIds,
            $userTimezoneOffset,
            true
        );
        /** @var Currency $currencyRub */
        $currencyRub = $this->repositoryProvider->get(Currency::class)->findOneBy(['name' => Currency::NAME_RUB]);
        $resultByMonthsConverted = $this->convertToRub(
            $currencyRub,
            $resultByMonths
        );
        $resultByDays = $paymentRepository->findByShopIdForChartByMonthsOrDaysWithOffset($shopIds, $userTimezoneOffset);
        $resultByDaysConverted = $this->convertToRub(
            $currencyRub,
            $resultByDays
        );
        $data = $this->compileView(
            $resultByMonthsConverted,
            $lastTwelveMonthsDataRange,
            $resultByDaysConverted,
            $lastThirtyDaysDataRange,
            ['total', 'success', 'amount']
        );

        return $data;
    }

    public function generateDataRangeWithZerosFormatted(
        string $interval,
        DateTimeZone $dateTimeZone
    ): array {
        $dataRange = [];
        if ($interval === self::INTERVAL_DAY) {
            $format = self::DAY_FORMAT;
            $length = self::INTERVAL_DAY_LENGTH;
        } elseif ($interval === self::INTERVAL_MONTH) {
            $format = self::MONTH_FORMAT;
            $length = self::INTERVAL_MONTH_LENGTH;
        } else {
            throw new RuntimeException("Unknown interval $interval");
        }
        for ($i = $length; $i >= 0; $i--) {
            $date = new DateTime();
            if ($interval === self::INTERVAL_MONTH) {
                $date->modify('first day of this month');
            }
            $date->modify("-$i $interval");
            $date->setTimezone($dateTimeZone);
            $intervalNumber = $date->format($format);
            $dataRange[$intervalNumber] = 0;
        }

        return $dataRange;
    }

    public function compileChartDataByIntervalRange(
        array $groupedByIntervalData,
        array $intervalRange,
        array $metricNames
    ): array {
        $result = [
            'intervals' => array_keys($intervalRange),
        ];
        $metrics = [];
        foreach ($metricNames as $metricName) {
            $metrics[$metricName] = array_column($groupedByIntervalData, $metricName, 'interval');
        }
        foreach ($intervalRange as $intervalNumber => $count) {
            foreach ($metricNames as $metricName) {
                $result[$metricName][] = $metrics[$metricName][$intervalNumber] ?? $count;
            }
        }

        return $result;
    }

    public function convertToRub(Currency $currencyRub, array $intervalsData): array
    {
        $result = [];
        foreach ($intervalsData as $intervalData) {
            $interval = $intervalData['interval'];
            if (bccomp($intervalData['amountSum'], '0.0', 2) !== 0) {
                $convertedAmount = $this->currencyConverter->convert(
                    $intervalData['currency'],
                    $currencyRub->id,
                    $intervalData['amountSum']
                );
            } else {
                $convertedAmount = 0;
            }
            if (!isset($result[$interval]['total'])) {
                $result[$interval]['total'] = 0;
            }
            if (!isset($result[$interval]['success'])) {
                $result[$interval]['success'] = 0;
            }
            $result[$interval]['success'] += $intervalData['successSum'];
            $result[$interval]['total'] += $intervalData['total'];
            $result[$interval]['interval'] = $intervalData['interval'];
            $sum = $result[$interval]['amount'] ?? '0.0';
            $result[$interval]['amount'] = bcadd($sum, $convertedAmount, 2);
        }

        return $result;
    }

    public function compileView(
        array $resultByMonths,
        array $monthsDataRange,
        array $resultByDays,
        array $daysDataRange,
        array $metricNames
    ): array {
        $view = [
            'byMonths' => $this->compileChartDataByIntervalRange(
                $resultByMonths,
                $monthsDataRange,
                $metricNames
            ),
            'byDays' => $this->compileChartDataByIntervalRange(
                $resultByDays,
                $daysDataRange,
                $metricNames
            ),
        ];

        return $view;
    }
}
