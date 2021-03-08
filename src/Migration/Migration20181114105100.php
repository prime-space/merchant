<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181114105100 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'pay methods code unoque index; user createdTs';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payoutMethod` ADD UNIQUE INDEX `code` (`code`);
ALTER TABLE `paymentMethod` ADD UNIQUE INDEX `code` (`code`);

ALTER TABLE `user` ADD COLUMN `createdTs` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `isBlocked`;
UPDATE `user` SET `createdTs` = '2000-01-01 00:00:00';
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payoutMethod` DROP INDEX `code`;
ALTER TABLE `paymentMethod` DROP INDEX `code`;

ALTER TABLE `user` DROP COLUMN `createdTs`;
SQL;
    }
}
