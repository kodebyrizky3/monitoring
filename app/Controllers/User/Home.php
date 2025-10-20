<?php
namespace App\Controllers\User;

use App\Controllers\BaseController;

class Home extends BaseController
{
    /** Bangun URL foto yang valid dari kolom employees.foto */
    private function buildFotoUrl(?string $raw): ?string
    {
        if (!$raw) return null;

        $raw = trim($raw);

        // Jika sudah absolute URL (CDN/HTTP), langsung pakai
        if (preg_match('~^https?://~i', $raw)) {
            return $raw;
        }

        // Asumsi DB menyimpan path RELATIF dari /public, contoh: "uploads/foto_user/emp_xxx.jpeg"
        $path = ltrim($raw, '/');                // pastikan tanpa leading slash
        $full = FCPATH . $path;                  // FCPATH = path ke folder public/

        // Kalau file ada di public/, pakai base_url($path)
        if (is_file($full)) {
            return base_url($path);
        }

        // (Opsi) Jika kamu menyimpan di writable/uploads, dan sudah ada route/handler untuk menyajikannya,
        // cukup return base_url('uploads/'.basename($path)); atau handler-mu sendiri.
        // Di sini kita tetap kembalikan base_url($path); JS fallback akan ganti ke placeholder bila 404.
        return base_url($path);
    }

    public function index()
    {
        // Harus login
        if (! session('isLoggedIn')) {
            session()->set('intended', site_url('user'));
            return redirect()->to(site_url('login'));
        }

        $db  = db_connect();
        $uid = (int) session('user_id');

        // Ambil profil user + pegawai + bidang
        $row = $db->table('users u')
            ->select('u.id as user_id, u.username, u.role, u.active, u.name,
                      e.id as employee_id, e.nama, e.kode_pegawai, e.foto, e.deleted_at as emp_deleted,
                      b.nama as bidang')
            ->join('employees e', 'e.id = u.employee_id', 'left')
            ->join('bidang b',    'b.id = e.bidang_id', 'left')   // pakai nama tabelmu: "bidang"
            ->where('u.id', $uid)
            ->get()->getRowArray();

        if (!$row) return redirect()->to(site_url('logout'));  // user nggak ada
        if ($row['role'] !== 'user') return redirect()->to(site_url('dashboard')); // bukan role user
        if (!empty($row['emp_deleted'])) {
            session()->setFlashdata('swal', [
                'icon'  => 'warning',
                'title' => 'Akun tidak aktif',
                'text'  => 'Data pegawai Anda berstatus arsip. Hubungi admin.',
            ]);
            return redirect()->to(site_url('logout'));
        }

        // Foto dari DB adalah path relatif semacam "uploads/foto_user/emp_xxx.jpeg" → jadikan URL
        $me = [
            'username'     => $row['username'],
            'nama'         => $row['nama'] ?: ($row['name'] ?: $row['username']),
            'kode_pegawai' => $row['kode_pegawai'] ?? null,
            'bidang'       => $row['bidang'] ?? null,
            'foto_url'     => $this->buildFotoUrl($row['foto'] ?? null),
        ];

        // TAMPILKAN LANDING PAGE (bukan halaman Kendaraan)
        return view('User/home/index', compact('me'));
    }

    /** Endpoint mini untuk angka-angka stat di kartu landing */
    public function stats()
    {
        if (! session('isLoggedIn') || session('role') !== 'user') {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'unauthorized']);
        }

        $db  = db_connect();
        $eid = (int) $db->table('users')->select('employee_id')->where('id', (int) session('user_id'))->get()->getRow('employee_id');

        // TODO: sesuaikan nama tabel/status dengan skema kamu
        $tripAktif = (int) $db->table('kendaraan_trips')
                        ->where('employee_id', $eid)
                        ->whereIn('status', ['AKTIF','DISETUJUI_ADMIN'])
                        ->countAllResults();
        $tiketAc   = (int) $db->table('ac_tickets')
                        ->where('pelapor_employee_id', $eid)
                        ->whereIn('status', ['MENUNGGU_ADMIN','PROSES_TEKNISI','DISETUJUI_ADMIN'])
                        ->countAllResults();
        $pending   = (int) $db->table('pengajuan')
                        ->where('employee_id', $eid)
                        ->where('status', 'MENUNGGU_ADMIN')
                        ->countAllResults();

        return $this->response->setJSON([
            'tripAktif' => $tripAktif,
            'tiketAc'   => $tiketAc,
            'pending'   => $pending,
        ]);
    }
}