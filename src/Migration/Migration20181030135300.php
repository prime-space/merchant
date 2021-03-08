<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181030135300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'test payment method';
    }
    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (8, 'test');
INSERT INTO `paymentMethod`
    (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `fee`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
    (19, 8, 3, 3, 0, 'Test', 'test', '', 'test', 0, 1);
UPDATE `paymentMethod` SET position = 5 WHERE id = 1;
UPDATE shop SET isTestMode = 0;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id IN (8);
DELETE FROM `paymentMethod` WHERE id IN (19);
UPDATE `paymentMethod` SET position = 0 WHERE id = 1;
SQL;
    }
}
