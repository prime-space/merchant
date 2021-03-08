<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180524051800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'notification';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `notification` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`paymentId` BIGINT(20) UNSIGNED NOT NULL,
	`statusId` TINYINT(3) NOT NULL,
	`data` TEXT NOT NULL,
	`result` TEXT NOT NULL,
	`httpCode` INT(20) UNSIGNED NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
DROP TABLE notification;
SQL;
    }
}
