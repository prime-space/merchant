<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180630230500 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentMethod externalCode';
    }

    public function up(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    ADD COLUMN `externalCode` VARCHAR (64) NOT NULL AFTER `code`;

INSERT INTO `paymentMethod` VALUES
    (6, 5, 3, 0, 'AdvCash', 'advcash', 'advcash_advcash_merchant_usd', 50, 0),
    (7, 5, 3, 0, 'МТС', 'mts', 'mts_rfibank_merchant_rub', 60, 0),
    (8, 5, 3, 0, 'Билайн', 'beeline', 'beeline_ipaycard_merchantMobile_rub', 70, 0),
    (9, 5, 3, 0, 'Мегафон', 'megafon', 'megafon_instapay_merchant_rub', 80, 0),
    (10, 5, 3, 0, 'Теле2', 'tele2', 'tele2_ipaycard_merchantMobile_rub', 90, 0),
    (11, 5, 3, 0, 'Payeer', 'mts', 'payeer_advcash_merchant_usd', 100, 0),
    (12, 5, 4, 0, 'World Card', 'world_card', 'visa_cpaytrz_merchant_uah', 110, 1),
    (13, 5, 4, 0, 'ПриватБанк', 'privat', 'visa_cpaytrz_merchant_uah', 120, 0);
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `paymentMethod`
    DROP COLUMN `externalCode`
SQL;
    }
}
