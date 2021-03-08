<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180829162400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentShot: successTs';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    ADD COLUMN `successTs` TIMESTAMP NULL DEFAULT NULL AFTER `createdTs`,
    ADD INDEX `successTs` (`successTs`);
UPDATE paymentShot SET successTs = createdTs;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
    DROP COLUMN `successTs`;
SQL;
    }
}
