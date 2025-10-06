<?php
namespace App\Models;
use CodeIgniter\Model;


class EmployeeModel extends Model
{
protected $table = 'employees';
protected $primaryKey = 'id';
protected $useAutoIncrement = true;
protected $returnType = 'array';
protected $allowedFields = ['kode_pegawai','nama','email','no_telp','is_active','created_at','updated_at'];
protected $useTimestamps = false; // kolom waktu di-DB


protected $validationRules = [
'kode_pegawai' => 'required|max_length[32]',
'nama' => 'required|max_length[120]',
'email' => 'permit_empty|valid_email|max_length[160]',
'no_telp' => 'permit_empty|max_length[32]|regex_match[/^[0-9+()\\-\\s]+$/]'
];
}