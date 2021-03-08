<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180429004000 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'currency';
    }

    public function up(): string
    {
        return <<<SQL
CREATE TABLE `currency` (
	`id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(32) NOT NULL,
	PRIMARY KEY (`id`)
)
COLLATE='utf8_general_ci'
ENGINE=InnoDB
;
INSERT INTO currency VALUES
    (1, 'usd'),
    (2, 'eur'),
    (3, 'rub'),
    (4, 'uah');
SQL;
    }

    public function down(): string
    {
        return <<<SQL
DROP TABLE currency;
SQL;
    }
}
