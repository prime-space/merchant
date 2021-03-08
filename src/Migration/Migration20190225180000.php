<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190225180000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'fastLog';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `fastLog` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `method` VARCHAR(32) NOT NULL,
    `methodId` BIGINT(20) UNSIGNED NOT NULL,
    `data` TEXT NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `method` (`method`),
    INDEX `methodId` (`methodId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE fastLog;
SQL;
    }
}
