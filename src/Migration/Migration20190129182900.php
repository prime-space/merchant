<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190129182900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Shop: postBackUrl';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `shop`
  ADD COLUMN `isPostbackEnabled` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `resultUrl`,
  ADD COLUMN `postbackUrl` VARCHAR(512) NOT NULL DEFAULT '' AFTER `isPostbackEnabled`;

ALTER TABLE `payment`
  ADD COLUMN `sub_id` VARCHAR(32) NOT NULL DEFAULT '' AFTER `payment`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `shop`
  DROP COLUMN `isPostbackEnabled`,
  DROP COLUMN `postbackUrl`;
  
ALTER TABLE `payment`
  DROP COLUMN `sub_id`;
SQL;
    }
}
