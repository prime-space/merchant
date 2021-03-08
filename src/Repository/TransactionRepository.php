<?php namespace App\Repository;

use App\Entity\Transaction;
use Ewll\DBBundle\Repository\Repository;

class TransactionRepository extends Repository
{
    /** @return Transaction[] */
    public function findTransactionsByUserAndFromTransactionId(int $userId, int $fromTransactionId): array
    {
        $prefix = 't1';
        $conditions = [
            "$prefix.userId = :userId",
        ];
        $params = [
            'userId' => $userId,
        ];
        if ($fromTransactionId !== 0) {
            $conditions[] = "$prefix.id < :fromTransactionId";
            $params['fromTransactionId'] = $fromTransactionId;
        }

        $implodedConditions = implode(' AND ', $conditions);
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE {$implodedConditions}
ORDER BY id DESC
LIMIT 5
SQL
        )->execute($params);

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    public function calcUnexecutedDecreaseTransactionSum($userId, $currencyId)
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT SUM(amount)
FROM {$this->config->tableName} $prefix
WHERE
    userId = :userId
    AND currencyId = :currencyId
    AND accountOperationId IS NULL
    AND amount < 0
SQL
            )
            ->execute([
                'userId' => $userId,
                'currencyId' => $currencyId,
            ]);
        $sum = $statement->fetchColumn();

        return $sum;
    }

    public function getBalance($userId, $currencyId)
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT balance
FROM {$this->config->tableName} $prefix
WHERE
    userId = :userId
    AND currencyId = :currencyId
    AND accountOperationId IS NOT NULL
ORDER BY accountOperationId DESC 
LIMIT 1
SQL
            )
            ->execute([
                'userId' => $userId,
                'currencyId' => $currencyId,
            ]);
        $balance = $statement->fetchColumn();

        return $balance;
    }

    /** @return Transaction[] */
    public function findByUserAndCurrencyWithPagination(int $userId, int $currencyId, int $pageId, int $limit): array
    {
        $prefix = 't1';
        $offset = ($pageId - 1) * $limit;
        $statement = $this->dbClient->prepare(<<<SQL
SELECT SQL_CALC_FOUND_ROWS {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.userId = :userId
    AND $prefix.currencyId = :currencyId
ORDER BY accountOperationId DESC
LIMIT $offset, $limit
SQL
        )->execute([
            'userId' => $userId,
            'currencyId' => $currencyId
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
