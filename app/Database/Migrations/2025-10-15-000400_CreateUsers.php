<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('users')) return;

        $this->forge->addField([
            'id'            => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
            'username'      => ['type'=>'VARCHAR','constraint'=>64,'null'=>false],
            'password_hash' => ['type'=>'VARCHAR','constraint'=>255,'null'=>false],
            'role'          => ['type'=>'VARCHAR','constraint'=>32,'null'=>false,'default'=>'user'],
            'active'        => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'name'          => ['type'=>'VARCHAR','constraint'=>120,'null'=>true],
            // SIAPKAN KOLOMNYA DI SINI (nullable). FK akan dipasang di migration berikut.
            'employee_id'   => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'null'=>true],
            'created_at'    => ['type'=>'DATETIME','null'=>true],
            'updated_at'    => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addKey('employee_id');

        $this->forge->createTable('users', true, [
            'ENGINE'  => 'InnoDB',
            'COMMENT' => 'Tabel Autentikasi',
            'DEFAULT CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('users', true);
    }
}
