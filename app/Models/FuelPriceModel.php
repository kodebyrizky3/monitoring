<?php
namespace App\Models;

use CodeIgniter\Model;

class FuelPriceModel extends Model
{
    protected $table          = 'fuel_prices';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['fuel_type','price_per_liter','effective_start','effective_end'];

    protected $validationRules = [
        'fuel_type'       => 'required|in_list[PERTALITE,PERTAMAX,SOLAR,DEX,LAINNYA]',
        'price_per_liter' => 'required|greater_than[0]',
        'effective_start' => 'required|valid_date',
        'effective_end'   => 'permit_empty|valid_date',
    ];
}
