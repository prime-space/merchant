<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180612172100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'qiwi';
    }
    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentMethod`
    (`id`, `paymentSystemId`, `currencyId`, `fee`, `name`, `code`, `position`, `enabled`)
VALUES
    (5, 3, 3, '0.00', 'Qiwi', 'qiwi', 40, 1);

ALTER TABLE `paymentShot`
    ADD INDEX `paymentMethodId_createdTs` (`paymentMethodId`, `createdTs`);

ALTER TABLE `paymentAccount`
    ADD COLUMN `isActive` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1' AFTER `enabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM paymentMethod WHERE id = 5;

ALTER TABLE `paymentShot`
    DROP INDEX `paymentMethodId_createdTs`;

ALTER TABLE `paymentAccount`
    DROP COLUMN `isActive`;
SQL;
    }
}
