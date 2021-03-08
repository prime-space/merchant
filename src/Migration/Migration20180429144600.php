<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180429144600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `payment` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`shopId` INT(10) UNSIGNED NOT NULL,
	`payment` BIGINT(20) UNSIGNED NOT NULL,
	`amount` DECIMAL(12,2) NOT NULL,
	`currency` TINYINT(3) NOT NULL,
	`description` VARCHAR(128) NOT NULL,
	`statusId` TINYINT(3) UNSIGNED NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `shopId_payment` (`shopId`, `payment`),
	INDEX `payment` (`payment`),
	INDEX `shopId_statusId` (`shopId`, `statusId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE payment;
SQL;
    }
}
