<?php
namespace App\Models;

use CodeIgniter\Model;

class VehicleModel extends Model
{
    protected $table            = 'vehicles';
    protected $primaryKey       = 'id';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $allowedFields    = [
        'plat','nama','fuel_type','kapasitas_tangki','km_per_liter','stok_liter_terkini','is_active'
    ];

    // penting: agar placeholder {id} tidak error
    protected $validationRules  = [
        'id'                 => 'permit_empty|is_natural_no_zero',
        'plat'               => 'required|min_length[4]|max_length[20]|is_unique[vehicles.plat,id,{id}]',
        'fuel_type'          => 'required|in_list[PERTALITE,PERTAMAX,SOLAR,DEX,LAINNYA]',
        'kapasitas_tangki'   => 'required|greater_than[0]',
        'km_per_liter'       => 'required|greater_than[0]',
        'stok_liter_terkini' => 'permit_empty|greater_than_equal_to[0]',
        'is_active'          => 'in_list[0,1]',
    ];
}
