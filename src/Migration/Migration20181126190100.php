<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181126190100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'transaction: accountId';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `transaction` ADD COLUMN `accountId` INT(10) UNSIGNED NULL DEFAULT NULL AFTER `userId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `transaction` DROP COLUMN `accountId`;
SQL;
    }
}
