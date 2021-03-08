<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class SessionRepository extends Repository
{
    public function deleteByHours($hours)
    {
        $this
            ->dbClient
            ->prepare(<<<SQL
DELETE FROM session
WHERE 
    TIMESTAMPDIFF(HOUR, createdTs, NOW()) > :hours
SQL
            )
            ->execute(['hours' => $hours]);
    }
}
