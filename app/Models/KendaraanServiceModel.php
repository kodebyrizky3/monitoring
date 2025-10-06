<?php
namespace App\Models;

use CodeIgniter\Model;

class KendaraanServiceModel extends Model
{
    protected $table            = 'kendaraan_services';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'kendaraan_id','source_ticket_id','pengaju_id','bengkel_nama','jenis_servis',
        'keluhan','tindakan','masuk_at','keluar_at','biaya','lampiran','status_servis',
        'created_at','updated_at'
    ];
}
