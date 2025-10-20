<?php
namespace App\Models;

use CodeIgniter\Model;

class BudgetModel extends Model
{
    protected $table          = 'budgets';
    protected $primaryKey     = 'id';
    protected $useTimestamps  = true;
    protected $allowedFields  = ['vehicle_id','bulan','tahun','budget_awal','sisa_budget'];

    protected $validationRules = [
        'vehicle_id'  => 'required|is_natural_no_zero',
        'bulan'       => 'required|greater_than_equal_to[1]|less_than_equal_to[12]',
        'tahun'       => 'required|greater_than_equal_to[2000]|less_than_equal_to[2100]',
        'budget_awal' => 'required|greater_than_equal_to[0]',
        'sisa_budget' => 'required|greater_than_equal_to[0]',
    ];
}
