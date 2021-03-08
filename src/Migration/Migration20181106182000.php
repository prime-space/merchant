<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181106182000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'systemAddBalance';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `systemAddBalance` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `accountId` INT(10) UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `comment` VARCHAR(128) NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `accountId` (`accountId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP table `systemAddBalance`;
SQL;
    }
}
