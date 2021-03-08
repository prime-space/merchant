<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190418143900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payop';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (16, 'payop');

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (29, 16, 2, 2, 2, 5.00, 1, 'World Card', 'card_eur', 'ca02bb20-4a2e-11e9-8ea9-2da5b10b6eb5', 'world_card', 60, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 16;
DELETE FROM `paymentMethod` WHERE id = 29;
SQL;
    }
}
