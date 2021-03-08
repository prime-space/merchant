<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180904211000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'account: lockCounter';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `account`
    ADD COLUMN `lockCounter` INT UNSIGNED NOT NULL DEFAULT '0' AFTER `lastTransactionId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `account`
    DROP COLUMN `lockCounter`;
SQL;
    }
}
