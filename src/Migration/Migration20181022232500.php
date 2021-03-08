<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181022232500 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payeer, bitcoin, perfect, sort all methods';
    }
    public function up(): string
    {
        return <<<SQL
UPDATE `paymentMethod` SET `position` = 0 WHERE id = 1;
UPDATE `paymentMethod` SET `position` = 10 WHERE id = 2;
UPDATE `paymentMethod` SET `position` = 20 WHERE id = 3;
UPDATE `paymentMethod` SET `position` = 30 WHERE id = 4;
UPDATE `paymentMethod` SET `position` = 40 WHERE id = 14;
UPDATE `paymentMethod` SET `position` = 50 WHERE id = 5;

UPDATE `paymentMethod` SET `position` = 60 WHERE id = 12;
UPDATE `paymentMethod` SET `position` = 60 WHERE id = 13;
UPDATE `paymentMethod` SET `position` = 60 WHERE id = 15;

UPDATE `paymentMethod` SET `position` = 70 WHERE id = 7;
UPDATE `paymentMethod` SET `position` = 80 WHERE id = 8;
UPDATE `paymentMethod` SET `position` = 90 WHERE id = 9;
UPDATE `paymentMethod` SET `position` = 100 WHERE id = 10;
UPDATE `paymentMethod` SET `position` = 110, `code` = 'payeer_' WHERE id = 11;
UPDATE `paymentMethod` SET `position` = 120 WHERE id = 6;

INSERT INTO `paymentMethod`
    (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
    (16, 7, 3, 3, NULL, 3.00, 'Payeer', 'payeer', '2609', 'payeer', 100, 1),
    (17, 5, 3, 3, NULL, 3.00, 'Perfect Money', 'pfmoney', 'perfectmoney_perfectmoney_merchant_usd', 'pfmoney', 130, 1),
    (18, 5, 3, 3, NULL, 3.00, 'Bitcoin', 'bitcoin', 'bitcoin_cubits_merchant_usd', 'bitcoin', 140, 1);

SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentMethod` WHERE id IN (16,17,18);
SQL;
    }
}
