<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180411154000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user table';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `shop` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` INT(10) UNSIGNED NOT NULL ,
	`name` VARCHAR(64) NOT NULL,
	`url` VARCHAR(128) NOT NULL,
	`description` VARCHAR(256) NOT NULL,
	`secret` VARCHAR(64) NOT NULL,
	`successUrl` VARCHAR(128) NOT NULL,
	`failUrl` VARCHAR(128) NOT NULL,
	`resultUrl` VARCHAR(128) NOT NULL,
	`testMode` TINYINT(3) UNSIGNED NOT NULL,
	`status` TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	INDEX `userId` (`userId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `shop`;
SQL;
    }
}
