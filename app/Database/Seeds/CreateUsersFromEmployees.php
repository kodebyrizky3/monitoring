<?php
namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CreateUsersFromEmployees extends Seeder
{
    public function run()
    {
        $db   = \Config\Database::connect();
        $empQ = $db->table('employees')
                   ->select('id, nama, kode_pegawai, email')
                   ->orderBy('id', 'ASC')
                   ->get();

        $rows = $empQ->getResultArray();

        // password default untuk semua akun baru (boleh ganti)
        $defaultPassword = '123';
        $passHash        = password_hash($defaultPassword, PASSWORD_DEFAULT);

        foreach ($rows as $r) {
            $baseUsername = strtolower(preg_replace('/\s+/', '', (string) $r['nama']));
            if ($baseUsername === '') {
                // fallback kalau nama kosong → pakai kode_pegawai atau "user{id}"
                $baseUsername = $r['kode_pegawai'] ?: ('user' . $r['id']);
            }

            // pastikan UNIQUE (append id jika sudah ada)
            $username = $baseUsername;
            $exists   = $db->table('users')->where('username', $username)->countAllResults();
            if ($exists > 0) {
                $username = $baseUsername . $r['id'];
            }

            // pakai INSERT IGNORE kalau uniknya tabrakan di micro race
            $db->table('users')->ignore(true)->insert([
                'username'      => $username,
                'password_hash' => $passHash,
                'role'          => 'user',
                'active'        => 1,
                'name'          => $r['nama'],
                'employee_id'   => $r['id'],
            ]);
        }

        echo "Done seeding users from employees.\n";
    }
}
