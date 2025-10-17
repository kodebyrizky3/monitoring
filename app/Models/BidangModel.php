<?php
namespace App\Models;

use CodeIgniter\Model;

class BidangModel extends Model
{
    protected $table         = 'bidang';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nama'];

    protected $useTimestamps = false;

    protected $validationRules = [
        'nama' => 'required|max_length[120]|is_unique[bidang.nama,id,{id}]',
    ];
    protected $validationMessages = [
        'nama' => [
            'required'   => 'Nama bidang wajib diisi.',
            'max_length' => 'Nama maksimal 120 karakter.',
            'is_unique'  => 'Nama bidang sudah ada.',
        ],
    ];
}
