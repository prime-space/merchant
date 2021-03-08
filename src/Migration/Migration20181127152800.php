<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181127152800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment: email';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment` ADD COLUMN `email` VARCHAR(256) NOT NULL DEFAULT '' AFTER `currency`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment` DROP COLUMN `email`;
SQL;
    }
}
