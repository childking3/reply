<?php

use humhub\components\Migration;

class uninstall extends Migration
{
    public function up()
    {
        $this->dropTable('mailreply_link');
    }

    public function down()
    {
        echo "uninstall does not support migration down.\n";
        return false;
    }
}
