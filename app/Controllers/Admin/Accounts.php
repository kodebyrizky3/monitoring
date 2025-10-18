<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Auth\UserModel;
use App\Models\EmployeeModel;

class Accounts extends BaseController
{
    // Buat akun untuk 1 pegawai
    public function store()
    {
        $post = $this->request->getPost();
        $employeeId = (int)($post['employee_id'] ?? 0);
        $username   = trim($post['username'] ?? '');
        $password   = (string)($post['password'] ?? '');
        $role       = $post['role'] ?? 'user';
        $mustChange = $this->request->getPost('must_change_password') ? 1 : 0;

        if (!$employeeId || $username === '' || $password === '') {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'employee_id, username, dan password wajib diisi',
                'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }
        if (!in_array($role, ['admin','user'], true)) $role = 'user';

        // validasi pegawai ada
        $emp = (new EmployeeModel())->find($employeeId);
        if (!$emp) {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'Pegawai tidak ditemukan',
                'csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }

        $users = new UserModel();

        // pastikan belum punya akun
        $existsByEmp = $users->where('employee_id', $employeeId)->first();
        if ($existsByEmp) {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'Pegawai ini sudah memiliki akun',
                'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }

        // username unik?
        $existsByUsername = $users->where('username', $username)->first();
        if ($existsByUsername) {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'Username sudah dipakai',
                'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }

        $users->insert([
            'employee_id'          => $employeeId,
            'username'             => $username,
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'role'                 => $role,
            'active'               => 1,
            'name'                 => $emp['nama'] ?? null,
            'must_change_password' => $mustChange,
        ]);

        return $this->response->setJSON([
            'success'=>true,
            'message'=>'Akun berhasil dibuat',
            'csrf'=>csrf_hash()
        ]);
    }

    // Reset password user
    public function reset($userId)
    {
        $password = (string)$this->request->getPost('password');
        $mustChange = $this->request->getPost('must_change_password') ? 1 : 0;

        if ($password === '') {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'Password baru wajib diisi',
                'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }

        $users = new UserModel();
        $u = $users->find($userId);
        if (!$u) {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'User tidak ditemukan',
                'csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }

        $users->update($userId, [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => $mustChange,
        ]);

        return $this->response->setJSON([
            'success'=>true,
            'message'=>'Password berhasil direset',
            'csrf'=>csrf_hash()
        ]);
    }

    // Toggle aktif/nonaktif user
    public function toggle($userId)
    {
        $users = new UserModel();
        $u = $users->find($userId);
        if (!$u) {
            return $this->response->setJSON([
                'success'=>false,
                'message'=>'User tidak ditemukan',
                'csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }

        $users->update($userId, ['active' => $u['active'] ? 0 : 1]);

        return $this->response->setJSON([
            'success'=>true,
            'message'=>'Status akun diperbarui',
            'active'=> (int)!$u['active'],
            'csrf'=>csrf_hash()
        ]);
    }
}
