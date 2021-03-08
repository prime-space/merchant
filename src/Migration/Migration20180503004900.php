<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180503004900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `paymentAccount` (
	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
	`paymentSystemId` TINYINT(3) UNSIGNED NOT NULL,
	`name` VARCHAR(64) NOT NULL,
	`config` TEXT NOT NULL,
	`weight` TINYINT(3) UNSIGNED NOT NULL,
	`enabled` SET('shop','merchant','withdraw') NOT NULL,
	PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
CREATE TABLE `paymentSystem` (
	`id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(64) NOT NULL,
	PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (1, 'yandex');
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (2, 'yandex_card');
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (3, 'qiwi');
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (4, 'robokassa');
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (5, 'interkassa');
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (6, 'webmoney');
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE paymentAccount;
DROP TABLE paymentSystem;
SQL;
    }
}
