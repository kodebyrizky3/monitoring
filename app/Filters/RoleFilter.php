<?php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $need = $arguments[0] ?? '';       // contoh: 'admin' atau 'user'
        $role = session('role') ?? '';

        // pastikan sudah login (kalau belum, lempar ke login)
        if (! session('isLoggedIn')) {
            session()->set('intended', current_url());
            return redirect()->to(site_url('login'));
        }

        // cek role
        if ($need && $role !== $need) {
            return redirect()->to(site_url('login'))->with('error', 'Akses khusus ' . $need . '.');
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
