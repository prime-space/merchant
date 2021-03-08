<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190613000900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payoutSet; payout change; payoutMethod: chunkSize';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `payoutSet` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `payoutMethodId` INT(10) UNSIGNED NOT NULL,
    `userId` INT(10) UNSIGNED NOT NULL,
    `internalUsersId` BIGINT(20) UNSIGNED NULL DEFAULT NULL,
    `accountId` INT(10) UNSIGNED NOT NULL,
    `paymentSystemId` TINYINT(3) UNSIGNED NOT NULL,
    `paymentAccountId` INT(10) UNSIGNED NULL DEFAULT NULL,
    `receiver` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(18,8) NOT NULL,
    `transferredAmount` DECIMAL(18,8) NOT NULL,
    `fee` DECIMAL(18,8) NOT NULL,
    `credit` DECIMAL(18,8) NOT NULL,
    `statusId` TINYINT(3) UNSIGNED NOT NULL,
    `chunkNum` INT(10) UNSIGNED NOT NULL,
    `chunkProcessedNum` INT(10) UNSIGNED NOT NULL DEFAULT '0',
    `chunkSuccessNum` INT(10) UNSIGNED NOT NULL DEFAULT '0',
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `userId_internalUsersId` (`userId`, `internalUsersId`),
    INDEX `internalUsersId` (`internalUsersId`),
    INDEX `receiver` (`receiver`),
    INDEX `statusId` (`statusId`),
    INDEX `payoutMethodId` (`payoutMethodId`)
)
ENGINE=InnoDB
;
ALTER TABLE `payout`
    ALTER `amount` DROP DEFAULT;
ALTER TABLE `payout`
    ADD COLUMN `payoutSetId` BIGINT(20) UNSIGNED NOT NULL AFTER `id`,
    ADD INDEX `payoutSetId` (`payoutSetId`),
    CHANGE COLUMN `payoutMethodId` `payoutMethodId` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `payoutSetId`,
    CHANGE COLUMN `userId` `userId` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `payoutMethodId`,
    CHANGE COLUMN `accountId` `accountId` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `internalUsersId`,
    CHANGE COLUMN `paymentSystemId` `paymentSystemId` TINYINT(3) UNSIGNED NULL DEFAULT NULL AFTER `accountId`,
    CHANGE COLUMN `receiver` `receiver` VARCHAR(64) NULL DEFAULT NULL AFTER `paymentAccountId`,
    CHANGE COLUMN `amount` `amount` DECIMAL(18,8) NOT NULL AFTER `receiver`,
    CHANGE COLUMN `fee` `fee` DECIMAL(12,2) NULL DEFAULT NULL AFTER `amount`,
    CHANGE COLUMN `credit` `credit` DECIMAL(18,8) NOT NULL AFTER `fee`;
ALTER TABLE `payoutMethod`
    ADD COLUMN `chunkSize` DECIMAL(18,8) NOT NULL DEFAULT '10000' AFTER `code`;
INSERT INTO payoutSet (
    id,
    payoutMethodId,
    userId,
    internalUsersId,
    accountId,
    paymentSystemId,
    paymentAccountId,
    receiver,
    amount,
    transferredAmount,
    fee,
    credit,
    statusId,
    chunkNum,
    chunkProcessedNum,
    chunkSuccessNum,
    createdTs
)
    SELECT
        id,
        payoutMethodId,
        userId,
        internalUsersId,
        accountId,
        paymentSystemId,
        paymentAccountId,
        receiver,
        amount,
        IF(statusId = 4, 0, amount),
        fee,
        credit,
        IF(statusId = 4, 4, 2),
        1,
        1,
        IF(statusId = 4, 0, 1),
        createdTs
    FROM payout;
UPDATE payout SET payoutSetId = id;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `payoutSet`;
ALTER TABLE `payout`
    ALTER `payoutMethodId` DROP DEFAULT,
    ALTER `userId` DROP DEFAULT,
    ALTER `accountId` DROP DEFAULT,
    ALTER `paymentSystemId` DROP DEFAULT,
    ALTER `receiver` DROP DEFAULT,
    ALTER `amount` DROP DEFAULT,
    ALTER `fee` DROP DEFAULT,
    ALTER `credit` DROP DEFAULT;
ALTER TABLE `payout`
    CHANGE COLUMN `payoutMethodId` `payoutMethodId` INT(10) UNSIGNED NOT NULL AFTER `id`,
    CHANGE COLUMN `userId` `userId` INT(10) UNSIGNED NOT NULL AFTER `payoutMethodId`,
    CHANGE COLUMN `accountId` `accountId` INT(10) UNSIGNED NOT NULL AFTER `internalUsersId`,
    CHANGE COLUMN `paymentSystemId` `paymentSystemId` TINYINT(3) UNSIGNED NOT NULL AFTER `accountId`,
    CHANGE COLUMN `receiver` `receiver` VARCHAR(64) NOT NULL AFTER `paymentAccountId`,
    CHANGE COLUMN `amount` `amount` DECIMAL(12,2) NOT NULL AFTER `receiver`,
    CHANGE COLUMN `fee` `fee` DECIMAL(12,2) NOT NULL AFTER `amount`,
    CHANGE COLUMN `credit` `credit` DECIMAL(12,2) NOT NULL AFTER `fee`,
    DROP COLUMN `payoutSetId`;
ALTER TABLE `payoutMethod`
    DROP COLUMN `chunkSize`;
SQL;
    }
}
