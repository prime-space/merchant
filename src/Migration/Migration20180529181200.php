<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180529181200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payment methods';
    }
    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentMethod`
    (`id`, `paymentSystemId`, `currencyId`, `name`, `code`, `position`, `enabled`)
VALUES
    (1, 6, 3, 'Webmoney R', 'wmr', 0, 0),
    (2, 6, 1, 'Webmoney Z', 'wmz', 10, 0),
    (3, 6, 2, 'Webmoney E', 'wme', 20, 0),
    (4, 6, 4, 'Webmoney U', 'wmu', 30, 0)
    ;

SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `paymentMethod` WHERE id IN (1,2,3,4);
SQL;
    }
}
