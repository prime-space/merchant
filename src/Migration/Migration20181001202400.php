<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181001202400 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'Payment system payeer';
    }

    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem` (`id`, `name`) VALUES (7, 'payeer');
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM paymentSystem WHERE id = 7;
SQL;
    }
}
