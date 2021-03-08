<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180605102900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'session table';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `session` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`key` VARCHAR(64) NOT NULL,
	`params` TEXT NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	INDEX `createdTs` (`createdTs`),
	UNIQUE INDEX `key` (`key`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `session`;
SQL;
    }
}
