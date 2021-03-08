<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class ShopRepository extends Repository
{
    public function findAllForAdminApiWithPagination(int $pageId, int $limit): array
    {
        $offset = ($pageId - 1) * $limit;
        $statement = $this->dbClient->prepare(<<<SQL
SELECT SQL_CALC_FOUND_ROWS s.id, s.url, s.userId, s.statusId, s.paymentDayLimit, pds.amount AS paymentDayLimitAmount
FROM shop s
LEFT JOIN (
    SELECT shopId, amount
    FROM paymentDayStatistic
    WHERE `date` = CURRENT_DATE
) pds ON pds.shopId = s.id
ORDER BY pds.amount DESC
LIMIT $offset, $limit
SQL
        )->execute();

        $items = $statement->fetchArrays();

        return $items;
    }

    public function findByLimitOffset($offset, $limit)
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
ORDER BY id DESC
LIMIT $offset, $limit
SQL
        )->execute();

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    public function getAllShopsCount()
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT COUNT(*)
FROM {$this->config->tableName} $prefix
SQL
        )->execute();

        return $statement->fetchColumn();
    }
}
