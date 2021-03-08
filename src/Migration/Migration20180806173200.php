<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180806173200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentAccount balance as array';
    }

    public function up(): string
    {
        return <<<SQL
UPDATE `paymentAccount` SET `balance` = 0;
ALTER TABLE `paymentAccount`
    CHANGE COLUMN `balance` `balance` TEXT NOT NULL DEFAULT '[]' AFTER `enabled`;
UPDATE `paymentAccount` SET `balance` = '[]';
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentAccount`
    CHANGE COLUMN `balance` `balance` DECIMAL(12,2) NULL DEFAULT NULL AFTER `enabled`;
SQL;
    }
}
