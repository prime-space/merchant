<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190314144700 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'advcash';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (15, 'advcash');

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (28, 15, 3, 3, null, 5.00, 1, 'Advcash', 'advcash_rub', 'ADVANCED_CASH', 'advcash', 150, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 15;
DELETE FROM `paymentMethod` WHERE id = 28;
SQL;
    }
}
