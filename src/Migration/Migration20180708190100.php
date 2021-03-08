<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180708190100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentShot initData; paymentMethod yandex';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    ADD COLUMN `initData` TEXT NOT NULL DEFAULT '[]' AFTER `fee`;

INSERT INTO `paymentMethod` VALUES
    (14, 1, 3, 0, 'Yandex Кошелек', 'yandex', '', 130, 1),
    (15, 1, 3, 0, 'Карты', 'card_rub', '', 140, 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    DROP COLUMN `initData`;
    
DELETE FROM `paymentMethod` WHERE id IN (14, 15);
SQL;
    }
}
