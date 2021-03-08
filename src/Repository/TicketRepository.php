<?php namespace App\Repository;

use Ewll\DBBundle\Repository\Repository;

class TicketRepository extends Repository
{
    public function getTicketsByUserIdOrderedByUnreadAndDate(int $userId)
    {
        $prefix = 't1';
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM ticket $prefix
WHERE
    $prefix.userId = :userId
ORDER BY
    $prefix.hasUnreadMessage DESC, $prefix.lastMessageTs DESC
SQL
        )->execute([
            'userId' => $userId,
        ]);

        $tickets = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $tickets;
    }
}
