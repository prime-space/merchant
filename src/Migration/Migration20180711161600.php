<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180711161600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentAccount assignedIds';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
	ADD COLUMN `assignedIds` TEXT NOT NULL DEFAULT '[]' AFTER `enabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
	DROP COLUMN `assignedIds`;
SQL;
    }
}
