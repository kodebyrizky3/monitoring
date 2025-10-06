<?php
namespace App\Models;
use CodeIgniter\Model;

class AlatModel extends Model
{
    protected $table = 'alat';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'kode_alat','tipe_alat','nama','lokasi','qr_token','status_operasional','catatan'
    ];

    public function total(): int { return $this->builder()->countAll(); }
    public function byTipe(string $tipe): int { return $this->where('tipe_alat',$tipe)->countAllResults(true); }
    public function rusakAktif(): int { return $this->where('status_operasional','rusak')->countAllResults(true); }
}
