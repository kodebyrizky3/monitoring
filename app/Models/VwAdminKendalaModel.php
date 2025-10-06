<?php
namespace App\Models;

use CodeIgniter\Model;

class VwAdminKendalaModel extends Model
{
    protected $table            = 'vw_admin_kendala';
    protected $primaryKey       = 'item_id';     // hanya agar CI senang; view ini gabungan
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [];            // view -> read-only
    protected $useTimestamps    = false;
}
