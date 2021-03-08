<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180506220100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentShot';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `paymentShot` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`paymentId` BIGINT(20) UNSIGNED NOT NULL,
	`paymentMethodId` INT(10) NOT NULL,
	`paymentAccountId` INT(10) NOT NULL,
	`statusId` TINYINT(3) NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	UNIQUE INDEX `paymentId_paymentMethodId` (`paymentId`, `paymentMethodId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE paymentShot;
SQL;
    }
}
