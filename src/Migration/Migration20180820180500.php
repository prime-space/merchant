<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180820180500 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'payoutMethods insert';
    }
    public function up(): string
    {
        return <<<SQL
INSERT INTO `payoutMethod`
    (`id`, `paymentSystemId`, `currencyId`, `fee`, `name`, `code`, `isEnabled`)
VALUES
    (1, 1, 3, 0.00, 'yandex', 'yandex', 1),
    (2, 3, 3, 0.00, 'qiwi', 'qiwi', 1),
    (3, 6, 3, 0.00, 'webmoney-r', 'webmoney_r', 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `payoutMethod` WHERE id IN (1,2,3);
SQL;
    }
}
