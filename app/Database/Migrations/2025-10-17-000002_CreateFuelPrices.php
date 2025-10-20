<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFuelPrices extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'              => ['type'=>'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
            'fuel_type'       => ['type'=>'ENUM','constraint'=>['PERTALITE','PERTAMAX','SOLAR','DEX','LAINNYA'],'default'=>'PERTALITE'],
            'price_per_liter' => ['type'=>'DECIMAL','constraint'=>'12,2','default'=>'0.00'],
            'effective_start' => ['type'=>'DATE'],
            'effective_end'   => ['type'=>'DATE','null'=>true], // null = masih berlaku
            'created_at'      => ['type'=>'DATETIME','null'=>true],
            'updated_at'      => ['type'=>'DATETIME','null'=>true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['fuel_type','effective_start']);
        $this->forge->createTable('fuel_prices', true);
    }

    public function down()
    {
        $this->forge->dropTable('fuel_prices', true);
    }
}
