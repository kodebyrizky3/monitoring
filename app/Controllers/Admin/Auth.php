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
            'password' => 'required|min_length[5]|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', 'Masukkan username & password yang valid.')->withInput();
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        $user = (new UserModel())->findActiveByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            usleep(250000); // kecilkan timing leak brute force
            return redirect()->back()->with('error', 'Username atau password salah.')->withInput();
        }

        // set session minimal
        session()->set([
            'isLoggedIn' => true,
            'user_id'    => (int) $user['id'],
            'username'   => $user['username'],
            'role'       => $user['role'], // 'admin' atau 'user'
            'name'       => $user['name'] ?? null,
        ]);
        session()->regenerate(); // cegah session fixation

        // redirect ke intended url kalau ada
        if ($intended = session()->get('intended')) {
            session()->remove('intended');

            // SweetAlert flash: login berhasil (intended)
            session()->setFlashdata('swal', [
              'icon' => 'success',
              'title' => 'Login berhasil',
              'text' => 'Selamat datang, ' . (($user['name'] ?? $user['username'])),
              'timer' => 1600,
              'showConfirmButton' => false,
              'timerProgressBar' => true,
            ]);

            return redirect()->to($intended);
        }

        // SweetAlert flash: login berhasil (default ke dashboard)
        session()->setFlashdata('swal', [
          'icon' => 'success',
          'title' => 'Login berhasil',
          'text' => 'Selamat datang, ' . (($user['name'] ?? $user['username'])),
          'timer' => 1600,
          'showConfirmButton' => false,
          'timerProgressBar' => true,
        ]);

        return redirect()->to(site_url('dashboard'));
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
