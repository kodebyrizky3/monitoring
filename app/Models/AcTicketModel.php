<?php
namespace App\Models;

use CodeIgniter\Model;

class AcTicketModel extends Model
{
    protected $table      = 'ac_tickets';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'ac_id','pelapor_id','pelapor_nama_snapshot','deskripsi_keluhan','foto_keluhan',
        'status_tiket','approved_by','approved_at','created_at','updated_at'
    ];
}
    