<?php
namespace App\Models\Auth;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'username', 'password_hash', 'role', 'active', 'name',
    ];

    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findActiveByUsername(string $username): ?array
    {
        return $this->where('username', $username)
                    ->where('active', 1)
                    ->first();
    }
}
