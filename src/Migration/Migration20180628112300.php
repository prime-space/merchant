<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180628112300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'session table';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `ticket` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`userId` INT(10) UNSIGNED NOT NULL,
	`subject` VARCHAR(256) NOT NULL,
	`hasUnreadMessage` TINYINT(3) UNSIGNED NOT NULL,
	`lastMessageTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
DROP TABLE `ticket`;
SQL;
    }
}
