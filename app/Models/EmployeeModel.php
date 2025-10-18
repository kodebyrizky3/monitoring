<?php
namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';

    // hanya kolom data pegawai (jangan masukkan username/password_hash karena itu di tabel users)
    protected $allowedFields    = [
        'kode_pegawai', 'nama', 'email', 'no_telp', 'bidang_id', 'is_active',
        'instagram_username','foto',
    ];

    // timestamps & soft delete
    protected $useTimestamps    = true;
    protected $useSoftDeletes   = true;
    protected $createdField     = 'created_at';   // <- pakai underscore
    protected $updatedField     = 'updated_at';   // <- pakai underscore
    protected $deletedField     = 'deleted_at';   // <- pakai underscore
}
