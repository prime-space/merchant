<?php namespace App\Repository;

use App\Entity\PaymentAccount;
use App\Entity\PaymentShot;
use Ewll\DBBundle\Repository\Repository;

class PaymentAccountRepository extends Repository
{
    const PAYMENT_SYSTEM_YANDEX_ID = 1;
    const PAYMENT_SYSTEM_YANDEX_CARD_ID = 2;
    const PAYMENT_SYSTEM_QIWI_ID = 3;

    /** @return PaymentAccount[] */
    public function getAllBySystemIdEnabledFor(int $paymentSystemId, string $enabledFor): array
    {
        $prefix = 't1';
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT {$this->getSelectList($prefix)}
FROM {$this->config->tableName} $prefix
WHERE
    paymentSystemId = :paymentSystemId
    AND FIND_IN_SET(:enabledFor, enabled) > 0
    AND isActive = 1
SQL
            )
            ->execute([
                'paymentSystemId' => $paymentSystemId,
                'enabledFor' => $enabledFor,
            ]);

        $items = $this->hydrator->hydrateMany(
            $this->config,
            $prefix,
            $statement,
            $this->getFieldTransformationOptions()
        );

        return $items;
    }

    public function resetAllBalances()
    {
        $this->dbClient->prepare(<<<SQL
UPDATE paymentAccount
SET balance = '[]'
SQL
        )->execute();
    }

    public function getUsingStat(): array
    {
        $paymentShotStatusIdSuccess = PaymentShot::STATUS_ID_SUCCESS;
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT
    IFNULL(ps.subPaymentAccountId, ps.paymentAccountId) AS payment_account_id,
    SUM(1) as `day`,
    SUM(IF(ps.successTs > ADDDATE(NOW(), INTERVAL -2 HOUR), 1, 0)) as hours,
    SUM(IF(ps.successTs > ADDDATE(NOW(), INTERVAL -15 MINUTE), 1, 0)) as minutes
FROM paymentShot ps
WHERE
    ps.successTs > ADDDATE(NOW(), INTERVAL -1 DAY)
    AND ps.statusId = $paymentShotStatusIdSuccess
GROUP BY IFNULL(ps.subPaymentAccountId, ps.paymentAccountId)
SQL
            )
            ->execute();
        $result = $statement->fetchArrays();

        return $result;
    }

    public function getTurnover(): array
    {
        $paymentShotStatusIdSuccess = PaymentShot::STATUS_ID_SUCCESS;
        $statement = $this
            ->dbClient
            ->prepare(<<<SQL
SELECT
    IFNULL(ps.subPaymentAccountId, ps.paymentAccountId) AS payment_account_id,
    SUM(IF(ps.createdTs > DATE_FORMAT(NOW(), "%Y-%m-01 00:00:00"), ps.amount, 0)) as currentMounth,
    SUM(IF(ps.createdTs < DATE_FORMAT(NOW(), "%Y-%m-01 00:00:00"), ps.amount, 0)) as lastMonth
FROM paymentShot ps
WHERE
    ps.createdTs > ADDDATE(DATE_FORMAT(NOW(), "%Y-%m-01 00:00:00"), INTERVAL -1 MONTH)
    AND ps.statusId = $paymentShotStatusIdSuccess
GROUP BY IFNULL(ps.subPaymentAccountId, ps.paymentAccountId)
SQL
            )
            ->execute();
        $result = $statement->fetchArrays();

        return $result;
    }
}
