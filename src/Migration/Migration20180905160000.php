<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180905160000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment notificationStatusId';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    ADD COLUMN `notificationStatusId` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1' AFTER `description`;
UPDATE `payment` SET `notificationStatusId` = '0';
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    DROP COLUMN `notificationStatusId`;
SQL;
    }
}
