<?php namespace App\Repository;

use App\Entity\PayoutSet;
use Ewll\DBBundle\Repository\Repository;

class PayoutSetRepository extends Repository
{
    public function increaseProcessed(PayoutSet $payoutSet, bool $success = false, string $transferredAmount = '0')
    {
        $increaseFields = ['chunkProcessedNum'];
        if ($success) {
            $increaseFields[] = 'chunkSuccessNum';
        }
        $increaseFieldsExpressions = [];
        foreach ($increaseFields as $increaseField) {
            $increaseFieldsExpressions[] = "$increaseField = $increaseField + 1";
        }
        $increaseFieldsExpressions[] = 'transferredAmount = transferredAmount + :transferredAmount';
        $expression = implode(', ', $increaseFieldsExpressions);
        $this->dbClient->prepare(<<<SQL
UPDATE payoutSet
SET $expression
WHERE id = :payoutSetId
SQL
        )->execute([
            'payoutSetId' => $payoutSet->id,
            'transferredAmount' => $transferredAmount,
        ]);
    }

    /** @return PayoutSet[] */
    public function findByFilterWithPaginationOrderByStatus(
        int $pageId,
        int $limit,
        array $filterRows = []
    ): array {
        $sqlParams = $filterRows;
        $prefix = 't1';
        $offset = ($pageId - 1) * $limit;
        $where = '';
        //@TODO ORDER BY by special column
        $payoutStatusIdNew = PayoutSet::STATUS_ID_NEW;
//        $payoutStatusIdUnknown = Payout::STATUS_ID_UNKNOWN;
//        $payoutStatusIdQueue = Payout::STATUS_ID_QUEUE;
//        $payoutStatusIdProcess = Payout::STATUS_ID_PROCESS;
//        $payoutStatusIdChecking = Payout::STATUS_ID_CHECKING;
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
ORDER BY
    CASE
        WHEN $prefix.statusId = $payoutStatusIdNew THEN 1
        ELSE 2
    END,
    $prefix.id DESC
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

    /** @return PayoutSet[] */
    public function findByUserIdFromIdLimited(int $userId, int $fromId): array
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT SQL_CALC_FOUND_ROWS {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.userId = :userId
    AND id >= :fromId
ORDER BY id
LIMIT 100
SQL
        )->execute([
            'userId' => $userId,
            'fromId' => $fromId,
        ]);

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }
}
