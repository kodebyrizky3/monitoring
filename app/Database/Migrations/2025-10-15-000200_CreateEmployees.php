<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployees extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('employees')) return;

        $this->forge->addField([
            'id'           => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
            'kode_pegawai' => ['type'=>'VARCHAR','constraint'=>32,'null'=>false],
            'nama'         => ['type'=>'VARCHAR','constraint'=>120,'null'=>false],
            'email'        => ['type'=>'VARCHAR','constraint'=>160,'null'=>true],
            'no_telp'      => ['type'=>'VARCHAR','constraint'=>32,'null'=>true],
            // LANGSUNG BUAT KOLOMNYA DI SINI (tanpa FK dulu)
            'bidang_id'    => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
            'username'     => ['type'=>'VARCHAR','constraint'=>64,'null'=>true],
            'password_hash'=> ['type'=>'VARCHAR','constraint'=>255,'null'=>true],
            'is_active'    => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'created_at'   => ['type'=>'DATETIME','null'=>true],
            'updated_at'   => ['type'=>'DATETIME','null'=>true],
            'deleted_at'   => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('kode_pegawai');
        $this->forge->addUniqueKey('username');
        $this->forge->addKey('bidang_id');

        $this->forge->createTable('employees', true, [
            'ENGINE'  => 'InnoDB',
            'COMMENT' => 'Data Pegawai',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('employees', true);
    }
}
