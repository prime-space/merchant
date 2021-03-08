<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181210175400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment: refundStatusId; user: lkMode; voucher';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    ADD COLUMN `refundStatusId` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1 AFTER `statusId`;
ALTER TABLE `user`
    ADD COLUMN `lkMode` VARCHAR(16) NOT NULL DEFAULT 'merchant' AFTER `pass`;
CREATE TABLE `voucher` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(64) NOT NULL,
    `method` VARCHAR(32) NOT NULL,
    `methodId` BIGINT(20) UNSIGNED NOT NULL,
    `currencyId` TINYINT(3) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `statusId` TINYINT(3) UNSIGNED NOT NULL,
    `userId` INT(10) UNSIGNED NULL DEFAULT NULL,
    `usedTs` TIMESTAMP NULL DEFAULT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `method` (`method`),
    INDEX `methodId` (`methodId`),
    UNIQUE INDEX `key` (`key`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    DROP COLUMN `refundStatusId`;
ALTER TABLE `user`
    DROP COLUMN `lkMode`;
DROP TABLE voucher;
SQL;
    }
}
