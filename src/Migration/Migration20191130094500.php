<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20191130094500 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: isDefaultExcluded';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    ADD COLUMN `isDefaultExcluded` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 AFTER `enabled`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    DROP COLUMN `isDefaultExcluded`
SQL;
    }
}
