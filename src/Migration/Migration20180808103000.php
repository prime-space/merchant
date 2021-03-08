<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180808103000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payoutMethod';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `payoutMethod` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`paymentSystemId` TINYINT(3) UNSIGNED NOT NULL,
	`currencyId` TINYINT(3) UNSIGNED NOT NULL,
	`fee` DECIMAL(4,2) NOT NULL,
	`name` VARCHAR(64) NOT NULL,
	`code` VARCHAR(64) NOT NULL,
	`isEnabled` TINYINT(3) UNSIGNED NOT NULL,
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
DROP TABLE payoutMethod;
SQL;
    }
}
