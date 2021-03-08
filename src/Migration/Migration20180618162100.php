<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180618162100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payout';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `payout` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `userId` INT(10) UNSIGNED NOT NULL,
    `accountId` INT(10) UNSIGNED NOT NULL,
    `paymentSystemId` TINYINT(3) UNSIGNED NOT NULL,
    `paymentAccountId` INT(10) UNSIGNED NULL,
    `receiver` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `fee` DECIMAL(12,2) NOT NULL,
    `statusId` TINYINT(3) UNSIGNED NOT NULL,
    `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `userId` (`userId`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;

UPDATE `paymentSystem` SET name = 'Yandex' WHERE id = 1;
UPDATE `paymentSystem` SET name = 'Yandex Card' WHERE id = 2;
UPDATE `paymentSystem` SET name = 'Qiwi' WHERE id = 3;
UPDATE `paymentSystem` SET name = 'Robokassa' WHERE id = 4;
UPDATE `paymentSystem` SET name = 'Interkassa' WHERE id = 5;
UPDATE `paymentSystem` SET name = 'Webmoney' WHERE id = 6;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP table `payout`;
UPDATE `paymentSystem` SET name = 'yandex' WHERE id = 1;
UPDATE `paymentSystem` SET name = 'yandex_card' WHERE id = 2;
UPDATE `paymentSystem` SET name = 'qiwi' WHERE id = 3;
UPDATE `paymentSystem` SET name = 'robokassa' WHERE id = 4;
UPDATE `paymentSystem` SET name = 'interkassa' WHERE id = 5;
UPDATE `paymentSystem` SET name = 'webmoney' WHERE id = 6;
SQL;
    }
}
