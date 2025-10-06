<?php
namespace App\Controllers\User\Kendaraan;

use App\Controllers\BaseController;

class Options extends BaseController
{
    // GET /u/options/kendaraan?q=&onlyReady=1&limit=300
    public function kendaraan()
    {
        $q         = trim((string)($this->request->getGet('q') ?? ''));
        $onlyReady = (string)($this->request->getGet('onlyReady') ?? '0');
        $limit     = max(1, (int)($this->request->getGet('limit') ?? 300));

        $db = \Config\Database::connect();
        $b  = $db->table('kendaraan_units')
                 ->select('id, no_polisi, merk_model, status_kendaraan, odometer_terakhir');

        if ($onlyReady === '1') {
            $b->where('status_kendaraan', 'SIAP');
        }
        if ($q !== '') {
            $b->groupStart()
              ->like('no_polisi', $q)
              ->orLike('merk_model', $q)
              ->groupEnd();
        }

        $rows = $b->orderBy('no_polisi', 'ASC')->limit($limit)->get()->getResultArray();

        $out = array_map(static function ($r) {
            return [
                'id'     => (int)$r['id'],
                'text'   => "{$r['no_polisi']} — {$r['merk_model']} ({$r['status_kendaraan']})",
                'odo'    => (int)$r['odometer_terakhir'],
                'status' => (string)$r['status_kendaraan'],
            ];
        }, $rows ?? []);

        return response()->setJSON([
            'success' => true,
            'rows'    => $out,
            'csrf'    => csrf_hash(),
        ]);
    }

    // GET /u/options/pegawai?q=&active=1&limit=300  (sudah OK sebelumnya)
    public function pegawai()
    {
        $q      = trim((string)($this->request->getGet('q') ?? ''));
        $active = (string)($this->request->getGet('active') ?? '1');
        $limit  = max(1, (int)($this->request->getGet('limit') ?? 300));

        $db = \Config\Database::connect();
        $b  = $db->table('employees')->select('id, nama, kode_pegawai, is_active');

        if ($active === '1') $b->where('is_active', 1);
        if ($q !== '') {
            $b->groupStart()->like('nama', $q)->orLike('kode_pegawai', $q)->groupEnd();
        }

        $rows = $b->orderBy('nama','ASC')->limit($limit)->get()->getResultArray();

        $out = array_map(static function ($r) {
            $kode = (string)($r['kode_pegawai'] ?? '');
            return ['id'=>(int)$r['id'], 'text'=> trim(($r['nama'] ?? '').($kode ? " — {$kode}" : ''))];
        }, $rows ?? []);

        return response()->setJSON([
            'success' => true,
            'rows'    => $out,
            'csrf'    => csrf_hash(),
        ]);
    }
}
