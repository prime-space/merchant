<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181018182100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment: userVars';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment` ADD COLUMN `userVars` TEXT NOT NULL DEFAULT '[]' AFTER `description`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL

ALTER TABLE `payment` DROP COLUMN `userVars`;
SQL;
    }
}
