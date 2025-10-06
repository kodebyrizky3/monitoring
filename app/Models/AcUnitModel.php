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
        'lokasi',
        'status_ac',
        'catatan',
        'foto_path',
    ];

    protected $useTimestamps = false;

    // HAPUS validationRules bawaan; validasi dilakukan di controller.
}
