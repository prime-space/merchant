<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20181130171300 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'new paymentSystem, payoutMethod';
    }
    public function up(): string
    {
        return <<<SQL
INSERT INTO `paymentSystem`
  (`id`, `name`)
VALUES
  (9, 'self');

INSERT INTO `payoutMethod`
  (`id`, `paymentSystemId`, `currencyId`, `fee`, `name`, `code`, `isEnabled`)
VALUES
  (4, 9, 3, 1.00, 'transfer', 'transfer', 1);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DELETE FROM `payoutMethod` WHERE id = 4;
DELETE FROM `paymentSystem` WHERE id = 9;
SQL;
    }
}
