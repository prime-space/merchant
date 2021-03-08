<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20190218120800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'PaymentShot: subPaymentAccountId';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
  ADD COLUMN `subPaymentAccountId` INT(10) NULL DEFAULT NULL AFTER `paymentAccountId`;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentShot`
  DROP COLUMN `subPaymentAccountId`;
SQL;
    }
}
