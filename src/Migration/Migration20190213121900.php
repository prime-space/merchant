<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190213121900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Freekassa';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (13, 'exchanger');

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (26, 13, 3, 3, 2, 3.00, 1, 'Карты', 'card_rub___', '', 'card', 60, 0);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 13;
DELETE FROM `paymentMethod` WHERE id = 26;
SQL;
    }
}
