<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180904105900 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment index: createdTs';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    ADD INDEX `createdTs` (`createdTs`);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `payment`
    DROP INDEX `createdTs`;
SQL;
    }
}
