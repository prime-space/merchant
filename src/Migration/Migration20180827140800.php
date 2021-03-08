<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180827140800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payout: payoutMethodId, internalUsersId';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    ADD COLUMN `payoutMethodId` INT(10) UNSIGNED NOT NULL AFTER `id`,
    ADD COLUMN `internalUsersId` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `userId`,
    ADD UNIQUE INDEX `userId_internalUsersId` (`userId`, `internalUsersId`),
    DROP INDEX userId;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    DROP INDEX userId_internalUsersId,
    DROP COLUMN `payoutMethodId`,
    DROP COLUMN `internalUsersId`,
    ADD INDEX `userId` (`userId`);
SQL;
    }
}
