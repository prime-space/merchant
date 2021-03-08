<?php namespace App\Repository;

use App\Entity\Account;
use Ewll\DBBundle\Repository\Repository;

class AccountRepository extends Repository
{
    public function getAccountWithLock(int $accountId): ?Account
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    id = :id
FOR UPDATE
SQL
            )
            ->execute([
                'id' => $accountId,
            ]);
        $item = $this->hydrator->hydrateOne($this->config, $prefix, $statement, $this->getFieldTransformationOptions());

        return $item;
    }

    public function incrementLockCounter(int $id)
    {
        $this
            ->dbClient
            ->prepare(<<<SQL
UPDATE {$this->config->tableName}
SET lockCounter = lockCounter + 1
WHERE 
    id = :id
SQL
            )
            ->execute(['id' => $id]);
    }
}
