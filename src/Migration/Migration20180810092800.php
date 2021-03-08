<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180810092800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentAccount isWhite';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
    ADD COLUMN `isWhite` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0' AFTER `assignedIds`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
    DROP COLUMN `isWhite`;
SQL;
    }
}
