<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190422172200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'mpay_card';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (17, 'mpay_card');

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (37, 17, 3, 3, 2, 5.00, 10, 'Карты', 'card_rub___', '', 'card', 60, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 17;
DELETE FROM `paymentMethod` WHERE id = 37;
SQL;
    }
}
