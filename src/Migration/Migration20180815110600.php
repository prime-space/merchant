<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180815110600 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user personalPayoutFees; shop personalPaymentFees';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`
    ADD COLUMN `personalPayoutFees` TEXT NOT NULL DEFAULT '[]' AFTER `apiIps`
;
ALTER TABLE `shop`
    ADD COLUMN `personalPaymentFees` TEXT NOT NULL DEFAULT '[]' AFTER `excludedMethodsByAdmin`
;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`
    DROP COLUMN `personalPayoutFees`;
ALTER TABLE `shop`
    DROP COLUMN `personalPaymentFees`;
SQL;
    }
}
