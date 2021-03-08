<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180524010400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentShot amount';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    ADD COLUMN `amount` DECIMAL(12,2) NOT NULL AFTER `statusId`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    DROP COLUMN `amount`;
SQL;
    }
}
