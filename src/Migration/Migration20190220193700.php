<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190220193700 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'gamemoney';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (14, 'gamemoney');

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (27, 14, 2, 2, 2, 3.00, 0.5, 'Карты EUR', 'card_eur', '', 'card', 60, 1);

ALTER TABLE `payment` ADD COLUMN `ip` VARCHAR(15) NULL DEFAULT NULL AFTER `statusId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 14;
DELETE FROM `paymentMethod` WHERE id = 27;

ALTER TABLE `payment` DROP COLUMN `ip`;

SQL;
    }
}
