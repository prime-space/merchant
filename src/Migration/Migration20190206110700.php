<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190206110700 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Mpay';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (11, 'mpay');

UPDATE `paymentMethod` SET code = 'mts_' WHERE id = 7;
UPDATE `paymentMethod` SET code = 'beeline_' WHERE id = 8;
UPDATE `paymentMethod` SET code = 'megafon_' WHERE id = 9;
UPDATE `paymentMethod` SET code = 'tele2_' WHERE id = 10;

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (21, 11, 3, 3, NULL, 3.00, 10, 'Билайн', 'beeline', '', 'beeline', 80, 1),
  (22, 11, 3, 3, NULL, 3.00, 10, 'Мегафон', 'megafon', '', 'megafon', 90, 1),
  (23, 11, 3, 3, NULL, 3.00, 10, 'МТС', 'mts', '', 'mts', 70, 1),
  (24, 11, 3, 3, NULL, 3.00, 10, 'Теле2', 'tele2', '', 'tele2', 100, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentSystem` WHERE id = 11;
DELETE FROM `paymentMethod` WHERE id = 21;
DELETE FROM `paymentMethod` WHERE id = 22;
DELETE FROM `paymentMethod` WHERE id = 23;
DELETE FROM `paymentMethod` WHERE id = 24;
UPDATE `paymentMethod` SET code = 'mts' WHERE id = 7;
UPDATE `paymentMethod` SET code = 'beeline' WHERE id = 8;
UPDATE `paymentMethod` SET code = 'megafon' WHERE id = 9;
UPDATE `paymentMethod` SET code = 'tele2' WHERE id = 10;
SQL;
    }
}
