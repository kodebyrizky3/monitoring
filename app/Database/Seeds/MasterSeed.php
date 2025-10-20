<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MasterSeed extends Seeder
{
    public function run()
    {
        // Vehicles
        $this->db->table('vehicles')->insertBatch([
            [
                'plat'=>'B-1234-XX','nama'=>'Avanza Dinas','fuel_type'=>'PERTALITE',
                'kapasitas_tangki'=>42,'km_per_liter'=>12.5,'stok_liter_terkini'=>20,'is_active'=>1
            ],
            [
                'plat'=>'B-5678-YY','nama'=>'Innova Dinas','fuel_type'=>'PERTAMAX',
                'kapasitas_tangki'=>55,'km_per_liter'=>10.2,'stok_liter_terkini'=>35,'is_active'=>1
            ],
        ]);

        // Fuel Prices
        $today = date('Y-m-d');
        $this->db->table('fuel_prices')->insertBatch([
            ['fuel_type'=>'PERTALITE','price_per_liter'=>10000,'effective_start'=>$today],
            ['fuel_type'=>'PERTAMAX','price_per_liter'=>13500,'effective_start'=>$today],
        ]);

        // Budgets (bulan berjalan)
        $bulan = (int)date('n'); $tahun = (int)date('Y');
        $v1 = $this->db->table('vehicles')->select('id')->where('plat','B-1234-XX')->get()->getRow('id');
        $v2 = $this->db->table('vehicles')->select('id')->where('plat','B-5678-YY')->get()->getRow('id');
        $this->db->table('budgets')->insertBatch([
            ['vehicle_id'=>$v1,'bulan'=>$bulan,'tahun'=>$tahun,'budget_awal'=>5000000,'sisa_budget'=>5000000],
            ['vehicle_id'=>$v2,'bulan'=>$bulan,'tahun'=>$tahun,'budget_awal'=>8000000,'sisa_budget'=>8000000],
        ]);
    }
}
