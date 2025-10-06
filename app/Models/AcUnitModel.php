<?php

namespace App\Models;

use CodeIgniter\Model;

class AcUnitModel extends Model
{
    protected $table            = 'ac_units';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    // kolom yang boleh diisi via insert/update
    protected $allowedFields    = [
        'kode_qr','nomor_unik','tipe_model','kapasitas_btu',
        'lokasi','status_ac','catatan','created_at','updated_at'
    ];

    // created_at & updated_at dikelola oleh DB (DEFAULT CURRENT_TIMESTAMP), jadi:
    protected $useTimestamps = false;

    // (opsional) rules dasar biar aman saat nanti create/edit
    protected $validationRules = [
        'kode_qr'       => 'required|min_length[3]|max_length[64]',
        'nomor_unik'    => 'required|min_length[2]|max_length[64]',
        'tipe_model'    => 'required|max_length[120]',
        'kapasitas_btu' => 'required|integer|greater_than[0]',
        'lokasi'        => 'required|max_length[120]',
        'status_ac'     => 'required|in_list[NORMAL,MENUNGGU_PERBAIKAN,DALAM_PERBAIKAN]',
        'catatan'       => 'permit_empty|string',
    ];
}
