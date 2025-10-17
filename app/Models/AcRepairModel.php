<?php
namespace App\Models;

use CodeIgniter\Model;

class AcRepairModel extends Model
{
    protected $table      = 'ac_repairs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'ticket_id','ac_id','teknisi_nama','tindakan','hasil_perbaikan',
        'foto_sebelum','foto_sesudah','biaya','submitted_at',
        'verifikasi_status','verified_by','verified_at'
    ];

    public function listByAc(int $acId): array
    {
        return $this->where('ac_id', $acId)
                    ->orderBy('submitted_at','DESC')
                    ->findAll();
    }
}
