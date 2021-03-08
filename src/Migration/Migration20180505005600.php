<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180505005600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment hash, paymentMethodId';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    ADD COLUMN `hash` VARCHAR(64) NOT NULL AFTER `currency`,
    ADD COLUMN `paymentMethodId` INT(10) UNSIGNED NULL AFTER `hash`,
    ADD UNIQUE INDEX `hash` (`hash`);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    DROP COLUMN `hash`,
    DROP COLUMN `paymentMethodId`;
SQL;
    }
}
