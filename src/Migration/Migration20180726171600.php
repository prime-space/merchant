<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180726171600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentAccount balance';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    ADD COLUMN `credit` DECIMAL(12,2) NOT NULL AFTER `fee`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payout`
    DROP COLUMN `credit`
SQL;
    }
}
