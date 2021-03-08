<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180809121600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user apiIp to apiIps';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    CHANGE COLUMN `apiIp` `apiIps` TEXT NOT NULL  DEFAULT '[]' AFTER `timezone`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    CHANGE COLUMN `apiIps` `apiIp` VARCHAR(15) NOT NULL DEFAULT '' AFTER `timezone`;
SQL;
    }
}
