<?php
namespace App\Controllers\User\Kendaraan;

use App\Controllers\BaseController;
use App\Models\KendaraanTicketModel;

class Tickets extends BaseController
{
    protected function ok($data = [], $message = 'OK')
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'csrf'    => csrf_hash(),
        ]);
    }
    protected function fail($errors = [], $message = 'Gagal', $code = 400)
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'csrf'    => csrf_hash(),
        ]);
    }
    private function uid(): int
    {
        helper('auth');
        if (function_exists('user') && user()) return (int) (user()->id ?? 0);
        return (int) (session('user_id') ?? 0);
    }
    private function uname(): string
    {
        helper('auth');
        if (function_exists('user') && user()) return (string) (user()->name ?? user()->username ?? 'User');
        return (string) (session('user_name') ?? 'User');
    }

    // GET /u/kendaraan/kerusakan/search
    public function search()
    {
        $q       = trim((string)($this->request->getGet('q') ?? ''));
        $status  = trim((string)($this->request->getGet('status') ?? ''));
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $m = new KendaraanTicketModel();
        $b = $m->select('kendaraan_tickets.*, ku.no_polisi')
               ->join('kendaraan_units ku','ku.id = kendaraan_tickets.kendaraan_id','left');

        if ($q !== '') {
            $b = $b->groupStart()
                   ->like('ku.no_polisi', $q)
                   ->orLike('kendaraan_tickets.deskripsi_keluhan', $q)
                   ->groupEnd();
        }
        if ($status !== '' && $status !== 'SEMUA') {
            $b = $b->where('kendaraan_tickets.status_tiket', $status);
        }

        $total = (clone $b)->select('kendaraan_tickets.id')->orderBy('', '', false)->countAllResults(false);
        $rows  = $b->orderBy('kendaraan_tickets.id','DESC')->limit($perPage, $offset)->get()->getResultArray();

        $rows = array_map(static function($r){
            return [
                'id'        => (int)$r['id'],
                'no_polisi' => (string)$r['no_polisi'],
                'deskripsi' => (string)$r['deskripsi_keluhan'],
                'status'    => (string)$r['status_tiket'],
                'waktu'     => (string)date('d M Y, H:i', strtotime($r['created_at'] ?? 'now')),
            ];
        }, $rows ?? []);

        return $this->response->setJSON([
            'success'   => true,
            'rows'      => $rows,
            'total'     => (int)$total,
            'perPage'   => (int)$perPage,
            'page'      => (int)$page,
            'pageCount' => (int)ceil(max($total,1)/$perPage),
            'csrf'      => csrf_hash(),
        ]);
    }

    // POST /u/kendaraan/kerusakan
    public function store()
    {
        $data = $this->request->getPost(['kendaraan_id','deskripsi']);
        $rules = [
            'kendaraan_id' => 'required|integer',
            'deskripsi'    => 'required|min_length[5]',
        ];
        if (! $this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 'Validasi gagal', 422);
        }

        // ===== Simpan Foto (optional)
        $path = null;
        $files = $this->request->getFiles();
        $fotos = $files['foto'] ?? null;
        if ($fotos) {
            $file = is_array($fotos) ? ($fotos[0] ?? null) : $fotos; // ambil satu pertama
            if ($file && $file->isValid() && ! $file->hasMoved()) {
                $dir = FCPATH . 'uploads/tickets';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);
                $newName = $file->getRandomName();
                $file->move($dir, $newName);
                $path = 'uploads/tickets/' . $newName; // simpan relatif
            }
        }

        $m = new KendaraanTicketModel();
        try {
            $m->insert([
                'kendaraan_id'          => (int)$data['kendaraan_id'],
                'pelapor_id'            => $this->uid(),
                'pelapor_nama_snapshot' => $this->uname(),
                'deskripsi_keluhan'     => trim($data['deskripsi']),
                'foto_keluhan'          => $path, // bisa null kalau tanpa foto
                'status_tiket'          => 'MENUNGGU_ADMIN',
                'created_at'            => date('Y-m-d H:i:s'),
                'updated_at'            => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return $this->fail([], $e->getMessage(), 400);
        }

        return $this->ok([], 'Laporan kerusakan dikirim');
    }
}
