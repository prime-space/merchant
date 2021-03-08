<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class UserRepository extends Repository
{
    public function findByEmailLikeLimited($str)
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM user $prefix
WHERE
    $prefix.email LIKE :str
LIMIT 5
SQL
        )->execute([
            'str' => "%$str%",
        ]);

        $users = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $users;
    }

    public function clearSessions()
    {
        $this->dbClient->prepare(<<<SQL
DELETE FROM userSession
WHERE lastActionTs < ADDDATE(NOW(), INTERVAL -2 DAY)
SQL
        )->execute();
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

    public function getAllUsersCount()
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
