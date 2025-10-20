<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBudgets extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'vehicle_id'   => ['type'=>'INT','constraint'=>11,'unsigned'=>true],
            'bulan'        => ['type'=>'TINYINT','constraint'=>2],  // 1..12
            'tahun'        => ['type'=>'SMALLINT','constraint'=>4],
            'budget_awal'  => ['type'=>'DECIMAL','constraint'=>'14,2','default'=>'0.00'], // Rupiah
            'sisa_budget'  => ['type'=>'DECIMAL','constraint'=>'14,2','default'=>'0.00'],
            'created_at'   => ['type'=>'DATETIME','null'=>true],
            'updated_at'   => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('vehicle_id','vehicles','id','CASCADE','RESTRICT');
        $this->forge->addUniqueKey(['vehicle_id','bulan','tahun']);
        $this->forge->createTable('budgets', true);
    }

    public function down()
    {
        $this->forge->dropTable('budgets', true);
    }
}
