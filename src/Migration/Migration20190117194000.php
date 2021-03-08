<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190117194000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Bitcoin';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (10, 'bitcoin');
  
ALTER TABLE `paymentMethod`
  ADD COLUMN `minimumAmount` DECIMAL(18,8) UNSIGNED NOT NULL DEFAULT '1' AFTER `fee`;
  
UPDATE `paymentMethod` SET `minimumAmount` = '0.02' WHERE id IN (2,3,17,18);
UPDATE `paymentMethod` SET `code` = 'bitcoin_' WHERE id = 18;

ALTER TABLE `paymentShot`
  CHANGE COLUMN `amount` `amount` DECIMAL(18,8) NOT NULL AFTER `statusId`;

ALTER TABLE `currency`
  ADD COLUMN `scale` TINYINT(3) UNSIGNED NOT NULL DEFAULT 2 AFTER `rate`;

INSERT INTO `currency`
  (`id`, `name`, `rate`, `scale`)
VALUES
  (5, 'btc', 1, 8);

INSERT INTO `paymentMethod`
  (`id`, `paymentSystemId`, `currencyId`, `currencyViewId`, `groupId`, `fee`, `minimumAmount`, `name`, `code`, `externalCode`, `img`, `position`, `enabled`)
VALUES
  (20, 10, 5, 5, NULL, 3.00, 0.00005, 'Bitcoin', 'bitcoin', '', 'bitcoin', 150, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `currency` DROP COLUMN `scale`;
ALTER TABLE `paymentMethod` DROP COLUMN `minimumAmount`;
ALTER TABLE `paymentShot`
  CHANGE COLUMN `amount` `amount` DECIMAL(12,2) NOT NULL AFTER `statusId`;
DELETE FROM `currency` WHERE id = 5;
DELETE FROM `paymentSystem` WHERE id = 10;
DELETE FROM `paymentMethod` WHERE id = 20;
UPDATE `paymentMethod` SET `code` = 'bitcoin' WHERE id = 18;
SQL;
    }
}
