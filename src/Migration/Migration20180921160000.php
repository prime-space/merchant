<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180921160000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'shop paymentDayLimit';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    ADD COLUMN `paymentDayLimit` DECIMAL(12,2) NOT NULL DEFAULT '100000' AFTER `secret`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    DROP COLUMN `paymentDayLimit`;
SQL;
    }
}
