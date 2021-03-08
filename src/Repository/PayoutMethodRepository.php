<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class PayoutMethodRepository extends Repository
{
    public function findWithWaitingIndexedByMethod()
    {
        $statement = $this->dbClient->prepare(<<<SQL
SELECT ps.payoutMethodId, SUM(p.credit) AS waiting
FROM payout p
JOIN payoutSet ps ON ps.id = p.payoutSetId
WHERE p.statusId IN (1,2)
GROUP BY ps.payoutMethodId
SQL
        )->execute();

        $data = $statement->fetchArrays();
        $items = [];
        foreach ($data as $row) {
            $items[$row['payoutMethodId']] = $row['waiting'];
        }

        return $items;
    }
}
