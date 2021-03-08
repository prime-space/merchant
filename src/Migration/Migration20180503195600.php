<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180503195600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `paymentMethod` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`paymentSystemId` TINYINT(3) UNSIGNED NOT NULL,
	`currencyId` TINYINT(3) UNSIGNED NOT NULL,
	`name` VARCHAR(64) NOT NULL,
	`code` VARCHAR(64) NOT NULL,
	`position` TINYINT(3) UNSIGNED NOT NULL,
	`enabled` TINYINT(3) UNSIGNED NOT NULL,
	PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE paymentMethod;
SQL;
    }
}
