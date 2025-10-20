<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVehicles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                 => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'plat'               => ['type'=>'VARCHAR','constraint'=>20],
            'nama'               => ['type'=>'VARCHAR','constraint'=>120,'null'=>true], // opsional (ex: Avanza Dinas)
            'fuel_type'          => ['type'=>'ENUM','constraint'=>['PERTALITE','PERTAMAX','SOLAR','DEX','LAINNYA'],'default'=>'PERTALITE'],
            'kapasitas_tangki'   => ['type'=>'DECIMAL','constraint'=>'8,2','default'=>'0.00'],   // liter
            'km_per_liter'       => ['type'=>'DECIMAL','constraint'=>'8,2','default'=>'0.00'],
            'stok_liter_terkini' => ['type'=>'DECIMAL','constraint'=>'10,2','default'=>'0.00'],
            'is_active'          => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
            'created_at'         => ['type'=>'DATETIME','null'=>true],
            'updated_at'         => ['type'=>'DATETIME','null'=>true],
            'deleted_at'         => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('plat');
        $this->forge->createTable('vehicles', true);
    }

    public function down()
    {
        $this->forge->dropTable('vehicles', true);
    }
}
    