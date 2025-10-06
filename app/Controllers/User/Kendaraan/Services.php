<?php
namespace App\Controllers\User\Kendaraan;

use App\Controllers\BaseController;
use CodeIgniter\Database\BaseConnection;

class Services extends BaseController
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

    // GET /u/kendaraan/perbaikan/search
    public function search()
    {
        /** @var BaseConnection $db */
        $db = \Config\Database::connect();

        $q       = trim((string)($this->request->getGet('q') ?? ''));
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $b = $db->table('kendaraan_services ks')
            ->select('ks.id, ks.kendaraan_id, ks.jenis_servis, ks.keluhan, ks.status_servis, COALESCE(ks.masuk_at, ks.created_at) AS waktu, ku.no_polisi')
            ->join('kendaraan_units ku', 'ku.id = ks.kendaraan_id', 'left');

        if ($q !== '') {
            $b->groupStart()
                  ->like('ku.no_polisi', $q)
                  ->orLike('ks.keluhan', $q)
                  ->orLike('ks.jenis_servis', $q)
              ->groupEnd();
        }

        $total = (clone $b)->select('ks.id')->orderBy('', '', false)->countAllResults(false);
        $rows  = $b->orderBy('COALESCE(ks.masuk_at, ks.created_at)', 'DESC')
                   ->limit($perPage, $offset)
                   ->get()->getResultArray();

        // map ke format yang dipakai u-kendaraan.js
        $rows = array_map(static function($r){
            return [
                'id'        => (int) $r['id'],
                'no_polisi' => (string) ($r['no_polisi'] ?? ''),
                'keluhan'   => (string) ($r['keluhan'] ?: ($r['jenis_servis'] ?? 'Perbaikan')),
                'status'    => (string) ($r['status_servis'] ?? 'DIAJUKAN'),
                'waktu'     => date('d M Y, H:i', strtotime($r['waktu'] ?? 'now')),
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

    // POST /u/kendaraan/perbaikan
    public function store()
    {
        $data = $this->request->getPost(['kendaraan_id','keluhan','odometer']);
        $rules = [
            'kendaraan_id' => 'required|integer',
            'keluhan'      => 'required|min_length[5]',
        ];
        if (! $this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 'Validasi gagal', 422);
        }

        // ===== Simpan Lampiran (multi) -> JSON array path relatif
        $paths = [];
        $files = $this->request->getFiles();
        $fotos = $files['foto'] ?? null;
        if ($fotos) {
            $arr = is_array($fotos) ? $fotos : [$fotos];
            $dirAbs = FCPATH . 'uploads/services';
            if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);

            foreach ($arr as $file) {
                if ($file && $file->isValid() && ! $file->hasMoved()) {
                    $newName = $file->getRandomName();
                    $file->move($dirAbs, $newName);
                    $paths[] = 'uploads/services/' . $newName; // simpan relatif
                }
            }
        }

        // Insert ke kendaraan_services
        $db = \Config\Database::connect();
        try {
            $db->table('kendaraan_services')->insert([
                'kendaraan_id'  => (int) $data['kendaraan_id'],
                'pengaju_id'    => $this->uid(),
                'bengkel_nama'  => null,
                'jenis_servis'  => null,
                'keluhan'       => trim($data['keluhan']),
                'tindakan'      => null,
                'masuk_at'      => date('Y-m-d H:i:s'),
                'keluar_at'     => null,
                'biaya'         => null,
                'lampiran'      => $paths ? json_encode($paths) : null,
                'status_servis' => 'DIAJUKAN', // default dari DB juga DIAJUKAN
            ]);
        } catch (\Throwable $e) {
            return $this->fail([], $e->getMessage(), 400);
        }

        return $this->ok([], 'Permintaan perbaikan dikirim');
    }
}
