<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180531184200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'transaction, account';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `transaction` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` INT(10) UNSIGNED NOT NULL,
	`method` VARCHAR(32) NOT NULL,
	`methodId` BIGINT(20) UNSIGNED NOT NULL,
	`amount` DECIMAL(12,2) NOT NULL,
	`currencyId` INT(10) UNSIGNED NOT NULL,
	`balance` DECIMAL(12,2) NULL,
	`accountOperationId` INT(10) UNSIGNED NULL,
	`executingTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	INDEX `userId` (`userId`),
	INDEX `method` (`method`),
	INDEX `methodId` (`methodId`),
	INDEX `currencyId` (`currencyId`),
	INDEX `accountOperationId` (`accountOperationId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

CREATE TABLE `account` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` INT(10) UNSIGNED NOT NULL,
	`currencyId` INT(10) UNSIGNED NOT NULL,
	`balance` DECIMAL(12,2) NOT NULL,
	`lastTransactionId` BIGINT(20) UNSIGNED NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `userId_currencyId` (`userId`, `currencyId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE transaction;
DROP TABLE account;
SQL;
    }
}
