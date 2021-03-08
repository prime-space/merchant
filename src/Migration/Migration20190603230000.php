<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190603230000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user excludedPayoutMethods; payoutMethod defaultExcluded';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `excludedPayoutMethods` TEXT NOT NULL DEFAULT '[]' AFTER `isBlocked`;
ALTER TABLE `payoutMethod`
    ADD COLUMN `defaultExcluded` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `isEnabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `excludedPayoutMethods`;
ALTER TABLE `payoutMethod`
    DROP COLUMN `defaultExcluded`
SQL;
    }
}
