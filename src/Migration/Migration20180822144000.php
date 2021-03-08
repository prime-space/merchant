<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180822144000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user isBlocked';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `isBlocked` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0' AFTER `isApiEnabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `isBlocked`;
SQL;
    }
}
