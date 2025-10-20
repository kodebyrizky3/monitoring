<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Auth\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        // kalau sudah login, lempar ke dashboard
        if (session()->get('isLoggedIn')) {
            return redirect()->to(site_url('dashboard'));
        }
        return view('Admin/auth/login', ['title' => 'Login']);
    }

    // proses form login (POST /auth/do)
    public function do()
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[50]',
            'password' => 'required|min_length[3]|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', 'Masukkan username & password yang valid.')->withInput();
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        // 1) Ambil user TANPA filter active
        $user = (new UserModel())->where('username', $username)->first();

        // Username tidak ada -> pesan generik
        if (!$user) {
            usleep(250000);
            return redirect()->back()->with('error', 'Username atau password salah.')->withInput();
        }

        // 2) Verifikasi password dulu (opsi "aman" agar tidak bocorkan status akun bila password salah)
        if (! password_verify($password, $user['password_hash'] ?? '')) {
            usleep(250000);
            return redirect()->back()->with('error', 'Username atau password salah.')->withInput();
        }

        // 3) Setelah password benar, beri pesan spesifik jika akun/login diblokir
        if ((int)($user['active'] ?? 0) !== 1) {
            return redirect()->back()->with('error', 'Akun login Anda dinonaktifkan oleh admin.')->withInput();
        }

        // Cek status pegawai terkait
        $emp = null;
        if (!empty($user['employee_id'])) {
            $emp = db_connect()->table('employees')
                ->select('id, is_active, deleted_at')
                ->where('id', (int)$user['employee_id'])
                ->get()->getRowArray();
        }

        if ($emp) {
            if (!empty($emp['deleted_at'])) {
                return redirect()->back()->with('error', 'Akun pegawai Anda diarsipkan. Hubungi admin.')->withInput();
            }
            if (isset($emp['is_active']) && (int)$emp['is_active'] === 0) {
                return redirect()->back()->with('error', 'Akun pegawai Anda dinonaktifkan. Hubungi admin.')->withInput();
            }
        }

        // 4) Lolos semua -> set session
        session()->set([
            'isLoggedIn' => true,
            'user_id'    => (int) $user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'] ?? 'user',
            'name'       => $user['name'] ?? null,
        ]);
        session()->regenerate();

        session()->setFlashdata('swal', [
            'icon' => 'success',
            'title' => 'Login berhasil',
            'text' => 'Selamat datang, ' . (($user['name'] ?? $user['username'])),
            'timer' => 1600,
            'showConfirmButton' => false,
            'timerProgressBar' => true,
        ]);

        // intended redirect
        if ($intended = session()->get('intended')) {
            session()->remove('intended');
            return redirect()->to($intended);
        }

        return redirect()->to(($user['role'] ?? '') === 'user' ? site_url('user') : site_url('dashboard'));
    }
    public function logout()
    {
        // 1) set flashdata dulu
        session()->setFlashdata('swal', [
            'icon' => 'success',
            'title' => 'Logout berhasil',
            'timer' => 1200,
            'showConfirmButton' => false,
            'timerProgressBar' => true,
        ]);

        // 2) hapus hanya data login (bukan destroy seluruh session)
        session()->remove(['isLoggedIn', 'user_id', 'username', 'role', 'name']);

        // 3) regenerate session id (aman) dan buang sesi lama
        session()->regenerate(true);

        // 4) redirect ke halaman login (login view harus include partial swal_flash)
        return redirect()->to(site_url('login'));
    }

}