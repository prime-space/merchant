<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190322145400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'masked: domain';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `masked` ADD COLUMN `domain` VARCHAR(64) NOT NULL DEFAULT '' AFTER `name`;
UPDATE `masked` SET `domain` = 'kasspers.com' WHERE id = 2;
UPDATE `masked` SET `domain` = 'payepe.com' WHERE id = 3;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `masked` DROP COLUMN `domain`;
SQL;
    }
}
