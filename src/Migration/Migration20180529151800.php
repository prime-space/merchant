<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180529151800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'currency rate';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `currency`
    ADD COLUMN `rate` DECIMAL(12,4) NOT NULL AFTER `name`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `currency`
    DROP COLUMN `rate`;
SQL;
    }
}
