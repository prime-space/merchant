<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180903140000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payout: indexes';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    ADD INDEX `internalUsersId` (`internalUsersId`),
    ADD INDEX `receiver` (`receiver`),
    ADD INDEX `statusId` (`statusId`),
    ADD INDEX `payoutMethodId` (`payoutMethodId`);
UPDATE payout
SET payoutMethodId = CASE
    WHEN paymentSystemId = 1 THEN 1
    WHEN paymentSystemId = 3 THEN 2
    WHEN paymentSystemId = 6 THEN 3
END
WHERE payoutMethodId = 0
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    DROP INDEX `internalUsersId`,
    DROP INDEX `receiver`,
    DROP INDEX `statusId`,
    DROP INDEX `payoutMethodId`;
SQL;
    }
}
