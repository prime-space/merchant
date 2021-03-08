<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190611005600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payout initData';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    ADD COLUMN `initData` TEXT NOT NULL DEFAULT '[]' AFTER `statusId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    DROP COLUMN `initData`;
SQL;
    }
}
