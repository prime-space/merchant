<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181221122000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'masked';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `masked` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(64) NOT NULL,
    `key` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `key` (`key`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
INSERT INTO `masked`
    (id, name, `key`)
VALUES
    (1, 'default', ''),
    (2, 'kasspers', MD5(UUID()));
    
ALTER TABLE `shop`
    ADD COLUMN `maskedId` INT(10) UNSIGNED NOT NULL DEFAULT '1' AFTER `userId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP table `masked`;
ALTER TABLE `shop`
    DROP COLUMN `maskedId`;
SQL;
    }
}
