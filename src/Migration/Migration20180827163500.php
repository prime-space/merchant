<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180827163500 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user isEmailConfirmed, emailConfirmationCode';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `emailConfirmationCode` VARCHAR (64) NULL DEFAULT NULL AFTER `apiSecret`,
    ADD COLUMN `isEmailConfirmed` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `isApiEnabled`,
    ADD UNIQUE INDEX `emailConfirmationCode` (`emailConfirmationCode`);
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `emailConfirmationCode`,
    DROP COLUMN `isEmailConfirmed`;
SQL;
    }
}
