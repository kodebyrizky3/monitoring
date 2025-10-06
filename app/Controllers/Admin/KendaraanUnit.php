<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\KendaraanUnitModel;

class KendaraanUnit extends BaseController
{
    protected function jsonOk($data = [], $message = 'OK')
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'csrf'    => csrf_hash(),
        ]);
    }
    protected function jsonFail($errors = [], $message = 'Gagal', $code = 400)
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'csrf'    => csrf_hash(),
        ]);
    }

    public function index()
    {
        // Halaman awal: render server-side pertama kali (bisa kosong atau sample rows)
        $m = new KendaraanUnitModel();
        $rows = $m->orderBy('id','DESC')->limit(10)->find(); // sample awal

        // KPI cepat
        $countTotal   = $m->countAllResults(false);
        $countSiap    = (clone $m)->where('status_kendaraan','SIAP')->countAllResults();
        $countDipakai = (clone $m)->where('status_kendaraan','DIPAKAI_DINAS')->countAllResults();
        $countBengkel = (clone $m)->where('status_kendaraan','DI_BENGKEL')->countAllResults();

        return view('Admin/kendaraan_units/index', [
            'rows'         => $rows,
            'countTotal'   => $countTotal,
            'countSiap'    => $countSiap,
            'countDipakai' => $countDipakai,
            'countBengkel' => $countBengkel,
            'q' => '', 'tipe' => '', 'status_kendaraan' => '',
            'activeMenu'  => 'kendaraan',
        ]);
    }

    // JSON detail utk modal edit (dipertahankan)
    public function json($id)
    {
        $m = new KendaraanUnitModel();
        $row = $m->find($id);
        if (!$row) return $this->jsonFail([], 'Data tidak ditemukan', 404);
        return $this->jsonOk($row);
    }

    // === Create (AJAX)
    public function store()
    {
        $rules = [
            'no_polisi'          => 'required|min_length[3]|max_length[20]|is_unique[kendaraan_units.no_polisi]',
            'merk_model'         => 'required|min_length[3]|max_length[120]',
            'tipe'               => 'required|in_list[MOBIL,MOTOR,TRUCK,BUS,LAINNYA]',
            'tahun'              => 'permit_empty|integer|greater_than_equal_to[1900]|less_than_equal_to['.date('Y').']',
            'odometer_terakhir'  => 'required|integer|greater_than_equal_to[0]',
            'status_kendaraan'   => 'required|in_list[SIAP,DIPAKAI_DINAS,DI_BENGKEL,MENUNGGU_PERBAIKAN]',
            'catatan'            => 'permit_empty|max_length[65535]',
        ];
        if (! $this->validate($rules)) {
            return $this->jsonFail($this->validator->getErrors(), 'Validasi gagal', 422);
        }
        $m = new KendaraanUnitModel();
        $m->insert([
            'no_polisi'         => trim($this->request->getPost('no_polisi')),
            'merk_model'        => trim($this->request->getPost('merk_model')),
            'tipe'              => $this->request->getPost('tipe'),
            'tahun'             => $this->request->getPost('tahun') ?: null,
            'odometer_terakhir' => (int)$this->request->getPost('odometer_terakhir'),
            'status_kendaraan'  => $this->request->getPost('status_kendaraan'),
            'catatan'           => $this->request->getPost('catatan'),
        ]);
        return $this->jsonOk([], 'Kendaraan berhasil ditambahkan');
    }

    // === Update (AJAX)
    public function update($id)
    {
        $m = new KendaraanUnitModel();
        if (! $m->find($id)) return $this->jsonFail([], 'Data tidak ditemukan', 404);

        $rules = [
            'no_polisi'          => 'required|min_length[3]|max_length[20]|is_unique[kendaraan_units.no_polisi,id,'.$id.']',
            'merk_model'         => 'required|min_length[3]|max_length[120]',
            'tipe'               => 'required|in_list[MOBIL,MOTOR,TRSCK,BUS,LAINNYA]',
            'tahun'              => 'permit_empty|integer|greater_than_equal_to[1900]|less_than_equal_to['.date('Y').']',
            'odometer_terakhir'  => 'required|integer|greater_than_equal_to[0]',
            'status_kendaraan'   => 'required|in_list[SIAP,DIPAKAI_DINAS,DI_BENGKEL,MENUNGGU_PERBAIKAN]',
            'catatan'            => 'permit_empty|max_length[65535]',
        ];
        // (typo safeguard untuk TRUCK)
        $rules['tipe'] = 'required|in_list[MOBIL,MOTOR,TRUCK,BUS,LAINNYA]';

        if (! $this->validate($rules)) {
            return $this->jsonFail($this->validator->getErrors(), 'Validasi gagal', 422);
        }

        $m->update($id, [
            'no_polisi'         => trim($this->request->getPost('no_polisi')),
            'merk_model'        => trim($this->request->getPost('merk_model')),
            'tipe'              => $this->request->getPost('tipe'),
            'tahun'             => $this->request->getPost('tahun') ?: null,
            'odometer_terakhir' => (int)$this->request->getPost('odometer_terakhir'),
            'status_kendaraan'  => $this->request->getPost('status_kendaraan'),
            'catatan'           => $this->request->getPost('catatan'),
        ]);
        return $this->jsonOk([], 'Perubahan disimpan');
    }

    // === Delete (AJAX)
    public function delete($id)
    {
        $m = new KendaraanUnitModel();
        try {
            $m->delete($id);
            return $this->jsonOk([], 'Data dihapus');
        } catch (\Throwable $e) {
            return $this->jsonFail([], $e->getMessage(), 400);
        }
    }

    // === LIVE SEARCH + PAGINATION (AJAX) ===
    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $tipe    = trim($this->request->getGet('tipe') ?? '');
        $status  = trim($this->request->getGet('status_kendaraan') ?? '');

        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $m  = new KendaraanUnitModel();
        $db = \Config\Database::connect();

        $base = $m->select(['id','no_polisi','merk_model','tipe','tahun','odometer_terakhir','status_kendaraan']);

        if ($tipe !== '')   $base = $base->where('tipe', $tipe);
        if ($status !== '') $base = $base->where('status_kendaraan', $status);

        if ($q !== '') {
            $exact  = $db->escape($q);
            $starts = $db->escape($q.'%');
            $like   = $db->escape('%'.$q.'%');

            $base = $base->groupStart()
                ->like('no_polisi', $q)
                ->orLike('merk_model', $q)
            ->groupEnd();

            // skor relevansi: exact > prefix > substring
            $scoreSql = "
              ((no_polisi  = {$exact})  * 120) +
              ((merk_model = {$exact})  * 110) +
              ((no_polisi  LIKE {$starts}) * 70) +
              ((merk_model LIKE {$starts}) * 60) +
              ((no_polisi  LIKE {$like})   * 20) +
              ((merk_model LIKE {$like})   * 18)
            AS score";

            $base = $base->select($scoreSql, false)
                         ->orderBy('score','DESC')
                         ->orderBy('merk_model','ASC')
                         ->orderBy('id','DESC');

            $countBuilder = clone $base;
            $total = $countBuilder->select('id')->orderBy('', '', false)->countAllResults(false);
        } else {
            $base  = $base->orderBy('id','DESC');
            $total = (clone $base)->select('id')->orderBy('', '', false)->countAllResults(false);
        }

        $rows = $base->limit($perPage, $offset)->get()->getResultArray();

        $data = array_map(static function($r){
            return [
                'id'                => (int)$r['id'],
                'no_polisi'         => (string)$r['no_polisi'],
                'merk_model'        => (string)$r['merk_model'],
                'tipe'              => (string)$r['tipe'],
                'tahun'             => isset($r['tahun']) ? (int)$r['tahun'] : null,
                'odometer_terakhir' => (int)$r['odometer_terakhir'],
                'status_kendaraan'  => (string)$r['status_kendaraan'],
                'delete_url'        => site_url('kendaraan/delete/'.$r['id']),
            ];
        }, $rows ?? []);

        return $this->response->setJSON([
            'success'    => true,
            'q'          => $q,
            'tipe'       => $tipe,
            'status'     => $status,
            'total'      => (int)$total,
            'perPage'    => (int)$perPage,
            'page'       => (int)$page,
            'pageCount'  => (int)ceil(max($total,1)/$perPage),
            'rows'       => $data,
            'csrf'       => csrf_hash(),
        ]);
    }
}
