<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190508153300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: alternativeId';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod` ADD COLUMN `alternativeId` INT(10) UNSIGNED NULL AFTER `position`;
UPDATE `paymentMethod` SET `alternativeId` = '37' WHERE id = 15;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod` DROP COLUMN `alternativeId`;
SQL;
    }
}
