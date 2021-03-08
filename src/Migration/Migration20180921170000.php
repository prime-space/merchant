<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180921170000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'paymentDayStatistic';
    }
    public function up(): string
    {
        return <<<SQL
CREATE TABLE `paymentDayStatistic` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `shopId` INT(10) UNSIGNED NOT NULL,
    `isLimitExceededEmailSent` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
    `amount` DECIMAL(12,2) NOT NULL DEFAULT '0',
    `date` DATE NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `shopId_date` (`shopId`, `date`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB;
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP table `paymentDayStatistic`;
SQL;
    }
}
