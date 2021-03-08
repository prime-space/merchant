<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class StatisticRepository extends Repository
{
    public function findForChartByMonthsOrDays(bool $byMonths = false): array
    {
        $prefix = 't1';
        $format = $byMonths ? '%m-%Y' : '%d-%m-%Y';
        $interval = $byMonths ? '12 MONTH' : '30 DAY';
        $formatMask = $byMonths ? '%Y-%m-01' : '%Y-%m-%d';
        $pointAmountCondition = $byMonths
            ? "$prefix.createdDate <= CONCAT(YEAR($prefix.createdDate), '-', MONTH($prefix.createdDate), '-', DAY(NOW()))"
            : '1=0';
            $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT 
    SUM($prefix.amount) AS 'amount',
    SUM(IF($pointAmountCondition, $prefix.amount, 0)) AS 'pointAmount',
    DATE_FORMAT($prefix.createdDate, '$format') AS 'interval'
FROM {$this->config->tableName} $prefix
WHERE
    $prefix.createdDate > DATE_FORMAT(NOW() - INTERVAL $interval, '$formatMask')
GROUP BY DATE_FORMAT($prefix.createdDate, '$format')
ORDER BY
    $prefix.id;
SQL
            )
            ->execute();
        $result = $statement->fetchArrays();

        return $result;
    }
}
