<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180924140300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: groupId, img; paymentMethodGroup';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    ADD COLUMN `groupId` TINYINT(3) UNSIGNED NULL DEFAULT NULL AFTER `currencyId`,
    ADD COLUMN `img` VARCHAR(64) NOT NULL DEFAULT '' AFTER `externalCode`
;

UPDATE paymentMethod SET groupId = 1, img = 'wmr'  WHERE id = 1;
UPDATE paymentMethod SET groupId = 1, img = 'wmz'  WHERE id = 2;
UPDATE paymentMethod SET groupId = 1, img = 'wme'  WHERE id = 3;
UPDATE paymentMethod SET groupId = 1, img = 'wmu'  WHERE id = 4;
UPDATE paymentMethod SET groupId = NULL, img = 'qiwi'  WHERE id = 5;
UPDATE paymentMethod SET groupId = NULL, img = 'advcash'  WHERE id = 6;
UPDATE paymentMethod SET groupId = NULL, img = 'mts'  WHERE id = 7;
UPDATE paymentMethod SET groupId = NULL, img = 'beeline'  WHERE id = 8;
UPDATE paymentMethod SET groupId = NULL, img = 'megafon'  WHERE id = 9;
UPDATE paymentMethod SET groupId = NULL, img = 'tele2'  WHERE id = 10;
UPDATE paymentMethod SET groupId = NULL, img = 'payeer', code = 'payeer'  WHERE id = 11;
UPDATE paymentMethod SET groupId = 2, img = 'world_card'  WHERE id = 12;
UPDATE paymentMethod SET groupId = 2, img = 'privat'  WHERE id = 13;
UPDATE paymentMethod SET groupId = NULL, img = 'yandex'  WHERE id = 14;
UPDATE paymentMethod SET groupId = 2, img = 'card'  WHERE id = 15;

CREATE TABLE `paymentMethodGroup` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(64) NOT NULL DEFAULT '',
    `img` VARCHAR(64) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;

INSERT INTO paymentMethodGroup
    (id, `key`, img)
VALUES
    (1, 'webmoney', 'wm'),
    (2, 'cards', 'card')
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    DROP COLUMN `groupId`,
    DROP COLUMN `img`
;
DROP TABLE paymentMethodGroup;
SQL;
    }
}
