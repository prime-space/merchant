<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181009102200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: currencyViewId';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    ADD COLUMN `currencyViewId` TINYINT(3) UNSIGNED NOT NULL AFTER `currencyId`
;
UPDATE paymentMethod SET currencyViewId = currencyId;
UPDATE paymentMethod SET currencyViewId = 4 WHERE id = 12;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    DROP COLUMN `currencyViewId`
;
SQL;
    }
}
