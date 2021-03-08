<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190315212800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: gamemoney change currency';
    }
    public function up(): string
    {
        return <<<SQL
UPDATE paymentMethod SET `currencyId`=3, `minimumAmount`=50 WHERE  `id`=27;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
UPDATE paymentMethod SET `currencyId`=2, `minimumAmount`=0.5 WHERE  `id`=27;
SQL;
    }
}
