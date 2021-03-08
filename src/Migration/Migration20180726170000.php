<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180726170000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentAccount balance';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
    ADD COLUMN `balance` DECIMAL(12,2) NULL DEFAULT NULL AFTER `enabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
    DROP COLUMN `balance`
SQL;
    }
}
