<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180424134000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'shop isFeeByClient';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    ALTER `testMode` DROP DEFAULT,
    ALTER `status` DROP DEFAULT;
ALTER TABLE `shop`
    CHANGE COLUMN `testMode` `isTestMode` TINYINT(3) UNSIGNED NOT NULL AFTER `resultUrl`,
    CHANGE COLUMN `status` `statusId` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1' AFTER `isTestMode`,
    ADD COLUMN `isFeeByClient` TINYINT(3) UNSIGNED NOT NULL AFTER `isTestMode`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `shop`
    ALTER `isTestMode` DROP DEFAULT,
    ALTER `statusId` DROP DEFAULT;
ALTER TABLE `shop`
    CHANGE COLUMN `isTestMode` `testMode` TINYINT(3) UNSIGNED NOT NULL AFTER `resultUrl`,
    CHANGE COLUMN `statusId` `status` TINYINT(3) UNSIGNED NOT NULL DEFAULT '1' AFTER `testMode`,
    DROP COLUMN `isFeeByClient`;
SQL;
    }
}
