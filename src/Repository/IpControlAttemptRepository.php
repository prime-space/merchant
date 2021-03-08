<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class IpControlAttemptRepository extends Repository
{
    public function deleteByLastMinutes($minutes)
    {
        $this
            ->dbClient
            ->prepare(<<<SQL
DELETE FROM ipControlAttempt
WHERE 
    TIMESTAMPDIFF(MINUTE, ipControlAttempt.createdTs, NOW()) > :minutes
SQL
            )
            ->execute(['minutes' => $minutes]);
    }

    public function deleteByIp($ip)
    {
        $this
            ->dbClient
            ->prepare(<<<SQL
DELETE FROM ipControlAttempt
WHERE 
    ip = :ip
SQL
            )
            ->execute(['ip' => $ip]);
    }

    public function isTooManyAttempts($ip)
    {
        $statement= $this->dbClient->prepare(<<<SQL
SELECT id
FROM ipControlAttempt
WHERE
    ip = :ip
LIMIT 3
SQL
            )
            ->execute(['ip' => $ip]);
        return count($statement->fetchArrays()) >= 3;
    }
}
