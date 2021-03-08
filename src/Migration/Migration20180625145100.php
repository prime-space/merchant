<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180625145100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'test lock';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `testLock` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `info` VARCHAR(64) NOT NULL,
    PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
INSERT INTO testLock VALUES (1, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE `testLock`;
SQL;
    }
}
