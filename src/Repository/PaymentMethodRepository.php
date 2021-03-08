<?php namespace App\Repository;

use App\Entity\Payment;
use App\Entity\PaymentMethod;
use Ewll\DBBundle\Repository\Repository;
use Ewll\DBBundle\Repository\RepositoryProvider;

class PaymentMethodRepository extends Repository
{
    private $repositoryProvider;

    public function __construct(RepositoryProvider $repositoryProvider)
    {
        $this->repositoryProvider = $repositoryProvider;
    }

    public function getAvailableMethods()
    {
        $testId = PaymentMethod::METHOD_TEST_ID;
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
JOIN paymentAccount pa ON pa.paymentSystemId = $prefix.paymentSystemId
WHERE 
    $prefix.id <> $testId
    AND $prefix.enabled = 1
    AND FIND_IN_SET('merchant', pa.enabled) > 0
    AND pa.isActive = 1
GROUP BY {$this->getGroupByList($prefix)}
ORDER BY $prefix.position
SQL
            )
            ->execute();

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    public function countDayStatByShop(int $shopId): array
    {
        $testId = PaymentMethod::METHOD_TEST_ID;
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT p.paymentMethodId AS id, SUM(p.amount) AS amount
FROM payment p
WHERE
    p.shopId = :shopId
    AND p.statusId = :successStatusId
    AND p.createdTs >= CURDATE()
    AND p.paymentMethodId <> $testId
GROUP BY p.paymentMethodId
SQL
            )
            ->execute([
                'shopId' => $shopId,
                'successStatusId' => Payment::STATUS_ID_SUCCESS
            ]);

        $data = $statement->fetchArrays();
        $stat = [];
        foreach ($data as $item) {
            $stat[$item['id']] = $item['amount'];
        }

        return $stat;
    }
}
