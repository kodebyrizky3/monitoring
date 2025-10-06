<?php
namespace App\Models;

use CodeIgniter\Model;

class KendaraanTripModel extends Model
{
    protected $table      = 'kendaraan_trips';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'kendaraan_id','pengemudi_id','pengemudi_nama_snapshot',
        'berangkat_at','odo_awal','fuel_awal','tujuan','foto_dash_berangkat',
        'lokasi_berangkat','kondisi_awal','estimasi_kembali_at','catatan_berangkat',
        'pulang_at','odo_akhir','fuel_akhir','foto_dash_pulang','lokasi_kembali',
        'kondisi_akhir','catatan_pulang','biaya_tambahan','status_trip'
    ];
    protected $useTimestamps = false;
}
