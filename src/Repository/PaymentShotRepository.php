<?php namespace App\Repository;

use App\Entity\PaymentShot;
use Ewll\DBBundle\Repository\Repository;

class PaymentShotRepository extends Repository
{
    /** @return PaymentShot[] */
    public function getLastShotsByPaymentMethodId(int $interval, int $holdTime, int $paymentMethodId)
    {
        $prefix = 't1';
        $statusIdWaiting = PaymentShot::STATUS_ID_WAITING;
        $statement = $this->dbClient->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM paymentShot $prefix
WHERE
    $prefix.createdTs > ADDDATE(NOW(), INTERVAL -$interval MINUTE)
    AND $prefix.createdTs < ADDDATE(NOW(), INTERVAL -$holdTime MINUTE)
    AND $prefix.paymentMethodId = :paymentMethodId
    AND $prefix.statusId = $statusIdWaiting
SQL
        )->execute([
            'paymentMethodId' => $paymentMethodId,
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
