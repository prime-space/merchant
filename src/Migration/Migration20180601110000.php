<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180601110000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'shop excluded methods';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    ADD COLUMN `excludedMethodsByUser` TEXT NOT NULL  DEFAULT '[]' AFTER `statusId`,
    ADD COLUMN `excludedMethodsByAdmin` TEXT NOT NULL DEFAULT '[]' AFTER `excludedMethodsByUser`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    DROP COLUMN `excludedMethodsByUser`,
    DROP COLUMN `excludedMethodsByAdmin`;
SQL;
    }
}
