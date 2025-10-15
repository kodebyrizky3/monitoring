<?php
namespace App\Models;

use CodeIgniter\Model;

class AcUnitModel extends Model
{
    protected $table      = 'ac_units';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'kode_qr',
        'nomor_unik',
        'tipe_model',
        'kapasitas_btu',
        'bmn_no_display',   // <-- Nomor BMN (kolom yang kita pakai)
        'serial_no',        // <-- Serial Number (kalau ada)
        'lokasi',
        'status_ac',
        'catatan',
        'foto_path',
    ];

    protected $useTimestamps = false;
}
