<?php
namespace App\Models;

use CodeIgniter\Model;

class KendaraanTicketModel extends Model
{
    protected $table      = 'kendaraan_tickets';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'kendaraan_id','pelapor_id','pelapor_nama_snapshot',
        'deskripsi_keluhan','foto_keluhan','status_tiket',
        'approved_by','approved_at','created_at','updated_at'
    ];
    protected $useTimestamps = false;
}
