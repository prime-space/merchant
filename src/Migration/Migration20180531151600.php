<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180531151600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment fee, credit; paymentMethod fee';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    ADD COLUMN `isFeeByClient` TINYINT(3) UNSIGNED NOT NULL AFTER `amount`,
    ADD COLUMN `fee` DECIMAL(12,2) NULL AFTER `isFeeByClient`,
    ADD COLUMN `credit` DECIMAL(12,2) NULL AFTER `fee`;
ALTER TABLE `paymentMethod`
    ADD COLUMN `fee` DECIMAL(4,2) NOT NULL AFTER `currencyId`;
ALTER TABLE `paymentShot`
    ADD COLUMN `fee` DECIMAL(12,2) NOT NULL AFTER `amount`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    DROP COLUMN `isFeeByClient`,
    DROP COLUMN `fee`,
    DROP COLUMN `credit`;
ALTER TABLE `paymentMethod`
    DROP COLUMN `fee`;
ALTER TABLE `paymentShot`
    DROP COLUMN `fee`;
SQL;
    }
}
