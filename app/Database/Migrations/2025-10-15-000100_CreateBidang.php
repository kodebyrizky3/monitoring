<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBidang extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('bidang')) return;

        $this->forge->addField([
            'id'         => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
            'nama'       => ['type'=>'VARCHAR','constraint'=>120,'null'=>false],
            'created_at' => ['type'=>'DATETIME','null'=>true],
            'updated_at' => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('nama');
        $this->forge->createTable('bidang', true, [
            'ENGINE'  => 'InnoDB',
            'COMMENT' => 'Master Bidang',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('bidang', true);
    }
}
