<?php
namespace App\Models;

use CodeIgniter\Model;

class KendaraanUnitModel extends Model
{
    protected $table      = 'kendaraan_units';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'no_polisi','merk_model','tipe','tahun','odometer_terakhir',
        'status_kendaraan','catatan','created_at','updated_at'
    ];
    protected $useTimestamps = false;
}
