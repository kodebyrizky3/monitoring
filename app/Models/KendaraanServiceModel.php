<?php
namespace App\Models;

use CodeIgniter\Model;

class KendaraanServiceModel extends Model
{
    protected $table            = 'kendaraan_services';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    public    $useTimestamps    = false; // tabel ini tidak pakai created_at by default

    protected $allowedFields = [
        'kendaraan_id','pengaju_id','bengkel_nama','jenis_servis','keluhan','tindakan',
        'masuk_at','keluar_at','biaya','lampiran','status_servis'
    ];
}
