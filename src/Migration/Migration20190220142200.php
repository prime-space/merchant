<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190220142200 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod: change card_rub_dir';
    }
    public function up(): string
    {
        return <<<SQL
UPDATE paymentMethod SET `code`='card_rub_dir' WHERE  `id`=15;
UPDATE paymentMethod SET `code`='card_rub' WHERE  `id`=26;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
UPDATE paymentMethod SET `code`='card_rub_exch' WHERE  `id`=26;
UPDATE paymentMethod SET `code`='card_rub' WHERE  `id`=15;
SQL;
    }
}
