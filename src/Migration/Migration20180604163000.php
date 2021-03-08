<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180604163000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'ip control attempt';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `ipControlAttempt` (
	`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`ip` VARCHAR(15) NOT NULL,
	`createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`id`),
	INDEX `ip` (`ip`),
	INDEX `createdTs` (`createdTs`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE ipControlAttempt;
SQL;
    }
}
