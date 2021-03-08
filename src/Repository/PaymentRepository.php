<?php namespace App\Repository;

use App\Entity\Payment;
use App\Entity\User;
use Ewll\DBBundle\Exception\NoAffectedRowsException;
use Ewll\DBBundle\Repository\Repository;

class PaymentRepository extends Repository
{
    public function findSuccessByShopWithPagination(int $shopId, int $pageId, int $limit): array
    {
        $prefix = 't1';
        $offset = ($pageId - 1) * $limit;
        $statement = $this->dbClient->prepare(<<<SQL
SELECT SQL_CALC_FOUND_ROWS {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.shopId = :shopId
    AND $prefix.statusId = :successStatusId
ORDER BY id DESC
LIMIT $offset, $limit
SQL
        )->execute([
            'shopId' => $shopId,
            'successStatusId' => $this->config->class::STATUS_ID_SUCCESS
        ]);

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    public function findByShopIdForChartByMonthsOrDaysWithOffset(
        array $shopIds,
        string $timeOffset,
        bool $byMonths = false
    ): array {
        $successStatusId = $this->config->class::STATUS_ID_SUCCESS;
        $prefix = 't1';
        $format = $byMonths ? '%m-%Y' : '%d-%m-%Y';
        $interval = $byMonths ? Payment::CHART_INTERVAL_MONTHS : Payment::CHART_INTERVAL_DAYS;
        $formatMask = $byMonths ? '%Y-%m-01' : '%Y-%m-%d';
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
SELECT 
    SUM($prefix.statusId = $successStatusId) AS 'successSum',
    SUM(1) AS 'total',
    $prefix.currency,
    SUM(CASE 
           WHEN $prefix.statusId = $successStatusId 
           THEN $prefix.credit 
           ELSE 0 
        END) AS 'amountSum',
    DATE_FORMAT(CONVERT_TZ($prefix.createdTs,'+00:00', '$timeOffset'), '$format') AS 'interval'
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.createdTs > DATE_FORMAT(CONVERT_TZ(NOW(),'+00:00', '$timeOffset') - INTERVAL $interval, '$formatMask')
    AND $prefix.shopId IN ($shopIdsPlaceholdersImploded)
GROUP BY $prefix.currency, DATE_FORMAT(CONVERT_TZ($prefix.createdTs,'+00:00', '$timeOffset'), '$format')
ORDER BY
    $prefix.id;
SQL
            )
            ->execute($sqlParams);
        $result = $statement->fetchArrays();

        return $result;
    }

    /** @throws NoAffectedRowsException*/
    public function initRefundAtomic(int $paymentId)
    {
        $affectedRows = $this->dbClient->prepare(<<<SQL
UPDATE payment
SET
  refundStatusId = :statusIdVoucherProvided
WHERE
  id = :id
  AND refundStatusId = :statusIdWasNot 
SQL
        )->execute([
            'statusIdWasNot' => Payment::REFUND_STATUS_ID_WAS_NOT,
            'statusIdVoucherProvided' => Payment::REFUND_STATUS_ID_VOUCHER_PROVIDED,
            'id' => $paymentId,
        ])->affectedRows();

        if ($affectedRows === 0) {
            throw new NoAffectedRowsException();
        }
    }

    public function findByEmailLikeLimited($str)
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM payment $prefix
WHERE
    $prefix.email LIKE :str
LIMIT 5
SQL
        )->execute([
            'str' => "%$str%",
        ]);

        $payments = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $payments;
    }

    /** @return Payment[] */
    public function findByFilterWithPagination(
        int $pageId,
        int $limit,
        array $filterRows = []
    ): array {
        $sqlParams = $filterRows;
        $prefix = 't1';
        $offset = ($pageId - 1) * $limit;
        $where = '';
        $paymentStatusIdSuccess = Payment::STATUS_ID_SUCCESS;
        foreach ($filterRows as $rowName => $value) {
//            if ($rowName === 'statusId' && $value === Payout::STATUS_ID_PROCESS) {
//                $where .= sprintf(
//                    ' AND (%1$s.%2$s = :%2$s OR %1$s.%2$s = \'%4$s\')',
//                    $prefix,
//                    $rowName,
//                    $value,
//                    Payout::STATUS_ID_QUEUE
//                );
//            } else {
            $where .= sprintf(' AND %1$s.%2$s = :%2$s', $prefix, $rowName);
//            }
        }
        $statement = $this->dbClient->prepare(<<<SQL
SELECT SQL_CALC_FOUND_ROWS {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
     1=1 $where
ORDER BY $prefix.id DESC
LIMIT $offset, $limit
SQL
        )->execute($sqlParams);

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    /** @throws NoAffectedRowsException*/
/*    public function refundAtomic(Payment $payment, User $refundUser)
    {
        $affectedRows = $this->dbClient->prepare(<<<SQL
UPDATE payment
SET
  refundStatusId = :statusIdUsed,
  refundUserId = :refundUserId
WHERE
  id = :id
  AND refundStatusId = :statusIdKeyProvided 
SQL
        )->execute([
            'statusIdUsed' => Payment::REFUND_STATUS_ID_USED,
            'statusIdKeyProvided' => Payment::REFUND_STATUS_ID_KEY_PROVIDED,
            'refundUserId' => $refundUser->id,
            'id' => $payment->id,
        ])->affectedRows();

        if ($affectedRows === 0) {
            throw new NoAffectedRowsException();
        }
    }*/
}
