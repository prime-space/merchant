<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180530145000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'redefine url';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    ADD COLUMN `isAllowedToRedefineUrl` TINYINT(3) UNSIGNED NOT NULL AFTER `isFeeByClient`
;
ALTER TABLE `payment`
    ADD COLUMN `successUrl` VARCHAR(256) NOT NULL AFTER `hash`,
    ADD COLUMN `failUrl` VARCHAR(256) NOT NULL AFTER `successUrl`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    DROP COLUMN `isAllowedToRedefineUrl`;
ALTER TABLE `payment`
    DROP COLUMN `successUrl`,
    DROP COLUMN `failUrl`;
SQL;
    }
}
