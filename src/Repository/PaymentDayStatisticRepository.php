<?php namespace App\Repository;

use App\Entity\PaymentDayStatistic;
use App\PaymentDayStatisticCounter;
use Ewll\DBBundle\Repository\Repository;

class PaymentDayStatisticRepository extends Repository
{
    public function findByShopIdsCurrentDayIndexedByShopId(array $shopIds)
    {
        $prefix = 't1';
        $sqlParams = [];
        $shopIdsPlaceholders = [];
        foreach ($shopIds as $shopIdKey => $shopId) {
            $placeholder = ":shopId_$shopIdKey";
            $sqlParams[$placeholder] = $shopId;
            $shopIdsPlaceholders[] = $placeholder;
        }
        $shopIdsPlaceholdersImploded = implode(',', $shopIdsPlaceholders);
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.date = CURDATE() 
    AND $prefix.shopId IN ($shopIdsPlaceholdersImploded)
SQL
            )
            ->execute($sqlParams);

        $paymentDayStatistics = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions(),
            'shopId'
        );

        return $paymentDayStatistics;
    }

    public function createOnDuplicateIncreaseAmount(PaymentDayStatistic $paymentDayStatistic, string $amount)
    {
        $sqlParams = $this->compileSqlParamsForInsert($paymentDayStatistic, $amount);
        $this
            ->dbClient
            ->prepare(<<<SQL
INSERT INTO {$this->config->tableName}
    (`shopId`, `isLimitExceededEmailSent`, `amount`, `date`)
VALUES
    (:shopId, :isLimitExceededEmailSent, :amount, :date)
ON DUPLICATE KEY UPDATE amount = amount + VALUES(`amount`)
SQL
            )
            ->execute($sqlParams);
    }

    public function getAffectedRowsCreateOrUpdateIsLimitExceededEmailSent(PaymentDayStatistic $paymentDayStatistic)
    {
        $sqlParams = $this->compileSqlParamsForInsert($paymentDayStatistic);
        $affectedRows = $this
            ->dbClient
            ->prepare(<<<SQL
INSERT INTO {$this->config->tableName}
    (`shopId`, `isLimitExceededEmailSent`, `amount`, `date`)
VALUES
    (:shopId, :isLimitExceededEmailSent, :amount, :date)
ON DUPLICATE KEY UPDATE isLimitExceededEmailSent = VALUES(`isLimitExceededEmailSent`)
SQL
            )
            ->execute($sqlParams)->affectedRows();

        return $affectedRows;
    }

    private function compileSqlParamsForInsert(PaymentDayStatistic $paymentDayStatistic, string $amount = null)
    {
        $sqlParams = [
            'shopId' => $paymentDayStatistic->shopId,
            'isLimitExceededEmailSent' => (int)$paymentDayStatistic->isLimitExceededEmailSent,
            'amount' => $amount ?? $paymentDayStatistic->amount,
            'date' => $paymentDayStatistic->date->format(PaymentDayStatisticCounter::DATE_FORMAT),
        ];

        return $sqlParams;
    }
}
