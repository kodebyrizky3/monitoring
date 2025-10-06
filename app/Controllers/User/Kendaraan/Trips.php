<?php
namespace App\Controllers\User\Kendaraan;

use App\Controllers\BaseController;
use App\Models\KendaraanTripModel;

class Trips extends BaseController
{
    // ===== helpers response =====
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

    // ===== helpers user =====
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

    // GET /u/kendaraan/perjalanan/search
    public function search()
    {
        $q       = trim((string)($this->request->getGet('q') ?? ''));
        $status  = trim((string)($this->request->getGet('status') ?? ''));
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $m = new KendaraanTripModel();
        $b = $m->select('kendaraan_trips.*, ku.no_polisi')
               ->join('kendaraan_units ku','ku.id = kendaraan_trips.kendaraan_id','left');

        if ($q !== '') {
            $b = $b->groupStart()
                   ->like('ku.no_polisi', $q)
                   ->orLike('kendaraan_trips.tujuan', $q)
                   ->groupEnd();
        }
        if ($status !== '' && $status !== 'SEMUA') {
            $b = $b->where('kendaraan_trips.status_trip', $status);
        }

        $total = (clone $b)->select('kendaraan_trips.id')->orderBy('', '', false)->countAllResults(false);
        $rows  = $b->orderBy('kendaraan_trips.id','DESC')->limit($perPage, $offset)->get()->getResultArray();

        $rows = array_map(static function($r){
            return [
                'id'        => (int)$r['id'],
                'no_polisi' => (string)$r['no_polisi'],
                'tujuan'    => (string)$r['tujuan'],
                'odo_awal'  => (int)$r['odo_awal'],
                'odo_akhir' => isset($r['odo_akhir']) ? (int)$r['odo_akhir'] : null,
                'status'    => (string)$r['status_trip'],
                'waktu'     => (string)date('d M Y, H:i', strtotime($r['berangkat_at'] ?? 'now')),
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

    // POST /u/kendaraan/perjalanan
    public function start()
    {
        $data = $this->request->getPost([
            'kendaraan_id','pengemudi_id','tujuan','berangkat_at',
            'odo_awal','fuel_awal','estimasi_kembali_at','lokasi_berangkat','catatan_berangkat'
        ]);
        $file = $this->request->getFile('foto_dash_berangkat');
    
        $rules = [
            'kendaraan_id' => 'required|integer',
            'pengemudi_id' => 'required|integer',
            'tujuan'       => 'required|min_length[3]|max_length[200]',
            'odo_awal'     => 'required|integer|greater_than_equal_to[0]',
            'fuel_awal'    => 'required|max_length[20]',
            'berangkat_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        ];
        if (! $this->validate($rules)) {
            return $this->jsonFail($this->validator->getErrors(), 'Validasi gagal', 422);
        }
        if (! $file || ! $file->isValid()) {
            return $this->jsonFail(['foto_dash_berangkat'=>'Wajib upload foto dashboard'], 'Validasi gagal', 422);
        }
    
        // ambil odometer_terakhir & status kendaraan untuk pre-cek cepat (trigger tetap final)
        $db = \Config\Database::connect();
        $ku = $db->table('kendaraan_units')->select('odometer_terakhir, status_kendaraan')
              ->where('id', (int)$data['kendaraan_id'])->get()->getRowArray();
        if (!$ku) return $this->jsonFail([], 'Kendaraan tidak ditemukan', 404);
    
        if ((int)$data['odo_awal'] < (int)$ku['odometer_terakhir']) {
            return $this->jsonFail(['odo_awal'=>'Harus ≥ odometer terakhir ('.$ku['odometer_terakhir'].')'], 'Validasi gagal', 422);
        }
        // optional: cegah cepat jika tidak SIAP (tetap biar trigger jadi kebenaran terakhir)
        if ($ku['status_kendaraan'] !== 'SIAP') {
            return $this->jsonFail([], 'Kendaraan tidak dalam status SIAP', 400);
        }
    
        // simpan foto
        $pathDir = WRITEPATH . 'uploads/trips';
        if (!is_dir($pathDir)) @mkdir($pathDir, 0775, true);
        $newName = 'start_' . time() . '_' . $file->getRandomName();
        $file->move($pathDir, $newName);
        $relPath = 'uploads/trips/' . $newName; // simpan relatif dari WRITEPATH
    
        $m = new \App\Models\KendaraanTripModel();
        try {
            $m->insert([
                'kendaraan_id'            => (int)$data['kendaraan_id'],
                'pengemudi_id'            => (int)$data['pengemudi_id'],
                'pengemudi_nama_snapshot' => null, // opsional; bisa isi dari employees bila perlu
                'berangkat_at'            => $data['berangkat_at'] ?: date('Y-m-d H:i:s'),
                'odo_awal'                => (int)$data['odo_awal'],
                'fuel_awal'               => trim($data['fuel_awal']),
                'tujuan'                  => trim($data['tujuan']),
                'foto_dash_berangkat'     => $relPath,
                'lokasi_berangkat'        => $data['lokasi_berangkat'] ?: null,
                'estimasi_kembali_at'     => $data['estimasi_kembali_at'] ?: null,
                'catatan_berangkat'       => $data['catatan_berangkat'] ?: null,
                'status_trip'             => 'AKTIF',
            ]);
        } catch (\Throwable $e) {
            // pesan dari trigger (tabrakan trip/servis, odo tidak valid, dll) akan muncul disini
            return $this->jsonFail([], $e->getMessage(), 400);
        }
    
        return $this->jsonOk([], 'Perjalanan dimulai');
    }
    

    // PUT /u/kendaraan/perjalanan/{id}/finish
    public function finish($id)
    {
        $id = (int) $id;
        if ($id <= 0) return $this->fail([], 'ID tidak valid', 400);

        $data = $this->request->getRawInput();
        $rules = [
            'odo_akhir' => 'required|integer|greater_than_equal_to[0]',
        ];
        if (! $this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 'Validasi gagal', 422);
        }

        $m = new KendaraanTripModel();
        if (! $m->find($id)) return $this->fail([], 'Trip tidak ditemukan', 404);

        try {
            $m->update($id, [
                'pulang_at'        => date('Y-m-d H:i:s'),
                'odo_akhir'        => (int)$data['odo_akhir'],
                'fuel_akhir'       => $data['fuel_akhir'] ?? null,
                'foto_dash_pulang' => '-', // placeholder agar lolos trigger
                'lokasi_kembali'   => $data['lokasi_kembali'] ?? null,
                'catatan_pulang'   => $data['catatan'] ?? null,
                'status_trip'      => 'SELESAI',
            ]);
        } catch (\Throwable $e) {
            return $this->fail([], $e->getMessage(), 400);
        }

        return $this->ok([], 'Perjalanan diselesaikan');
    }
}
