<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181012230200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'letter: userId is null';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `letter` CHANGE COLUMN `userId` `userId` INT(10) UNSIGNED NULL AFTER `id`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL

ALTER TABLE `letter` CHANGE COLUMN `userId` `userId` INT(10) UNSIGNED NOT NULL AFTER `id`;
SQL;
    }
}
