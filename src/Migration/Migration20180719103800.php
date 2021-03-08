<?php namespace App\Migration;

use Ewll\DBBundle\Migration\MigrationInterface;

class Migration20180719103800 implements MigrationInterface
{
    public function getDescription(): string
    {
        return 'user apiSecret add default value';
    }
    public function up(): string
    {
        return <<<SQL
ALTER TABLE `user`                                                                                 
	CHANGE COLUMN `apiSecret` `apiSecret` VARCHAR (64) NOT NULL DEFAULT '' AFTER `apiIp`;       
SQL;
    }

    public function down(): string
    {
        return <<<SQL
ALTER TABLE `user`                                                                                 
	CHANGE COLUMN `apiSecret` `apiSecret` VARCHAR (64) NOT NULL AFTER `apiIp`;       
SQL;
    }
}
