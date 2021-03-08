<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180625163600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user apiIp, timezone, apiSecret, isApiEnabled';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `timezone` VARCHAR (30) NOT NULL DEFAULT 'Atlantic/Reykjavik' AFTER `pass`,
    ADD COLUMN `apiIp` VARCHAR(15) NOT NULL DEFAULT '' AFTER `timezone`,
    ADD COLUMN `apiSecret` VARCHAR (64) NOT NULL AFTER `apiIp`,
    ADD COLUMN `isApiEnabled` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `apiSecret`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `timezone`,
    DROP COLUMN `apiIp`,
    DROP COLUMN `apiSecret`,
    DROP COLUMN `isApiEnabled`;
SQL;
    }
}
