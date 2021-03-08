<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181003100000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user: doNotAskPassUntilTs';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `doNotAskPassUntilTs` TIMESTAMP NULL DEFAULT NULL AFTER `isEmailConfirmed`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `doNotAskPassUntilTs`;
SQL;
    }
}
