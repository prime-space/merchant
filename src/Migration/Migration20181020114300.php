<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181020114300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'statistic';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `statistic` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `method` VARCHAR(32) NOT NULL,
    `methodId` BIGINT(20) UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `createdDate` DATE NOT NULL DEFAULT CURRENT_DATE,
    PRIMARY KEY (`id`),
    INDEX `createdDate` (`createdDate`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE statistic;
SQL;
    }
}
