<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\VehicleModel;
use Config\Database;

class Vehicles extends BaseController
{
    public function index()
    {
        $m = new VehicleModel();

        // angka ringkas utk stat di atas
        $countTotal = $m->where('deleted_at', null)->countAllResults(false);

        // render awal (server-side) — tampilkan 10 rows pertama agar tidak blank
        $rows = $m->orderBy('id', 'DESC')->findAll(10);

        return view('Admin/master/vehicles_index', [
            'title'      => 'Master Kendaraan',
            'activeMenu'  => 'vehicle.list',
            'countTotal' => $countTotal,
            'rows'       => $rows, 
            'perPage'    => 10,
            'q'          => ''
        ]);
    }

    // JSON list + paging (dipanggil JS)
    public function search()
    {
        $q       = trim((string) $this->request->getGet('q'));
        $perPage = (int)($this->request->getGet('perPage') ?? 10);
        $page    = (int)($this->request->getGet('page') ?? 1);
        $scope   = (string)($this->request->getGet('scope') ?? 'all'); // all|archived|active

        if ($perPage < 1) $perPage = 10;
        if ($page < 1)    $page    = 1;

        $m = new \App\Models\VehicleModel();

        // scope
        if ($scope === 'archived') {
            $m = $m->onlyDeleted();              // hanya soft-deleted
        } else {
            $m = $m->where('deleted_at', null);  // default: yang tidak terhapus
            if ($scope === 'active') {
                $m = $m->where('is_active', 1);
            }
        }

        // filter q
        if ($q !== '') {
            $m->groupStart()->like('plat',$q)->orLike('nama',$q)->groupEnd();
        }

        // total sesuai filter
        $total = $m->countAllResults(false);

        // page data
        $m->orderBy('id','DESC')->limit($perPage, ($page-1)*$perPage);
        $rows = $m->find();

        // action URLs
        $rows = array_map(function($r){
            $id = (int)$r['id'];
            $r['delete_url']  = site_url('admin/master/vehicles/'.$id.'/delete');
            $r['restore_url'] = site_url('admin/master/vehicles/'.$id.'/restore');
            return $r;
        }, $rows ?? []);

        return $this->response->setJSON([
            'success'   => true,
            'rows'      => $rows,
            'total'     => (int)$total,
            'page'      => (int)$page,
            'pageCount' => max(1, (int)ceil($total/$perPage)),
            'csrf'      => csrf_hash(),
        ]);
    }

    // untuk isi form edit
    public function show($id)
    {
        $row = (new VehicleModel())->find((int)$id);
        if (!$row) {
            return $this->response->setStatusCode(404)->setJSON([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'csrf'    => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success' => true,
            'data'    => $row,
            'csrf'    => csrf_hash(),
        ]);
    }

    public function store()
    {
        $data = [
            'plat'               => $this->request->getPost('plat'),
            'nama'               => $this->request->getPost('nama'),
            'fuel_type'          => $this->request->getPost('fuel_type'),
            'kapasitas_tangki'   => $this->request->getPost('kapasitas_tangki'),
            'km_per_liter'       => $this->request->getPost('km_per_liter'),
            'stok_liter_terkini' => $this->request->getPost('stok_liter_terkini') ?: 0,
            'is_active'          => $this->request->getPost('is_active') ?? 1,
        ];
        $m = new VehicleModel();
        if (!$m->insert($data)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $m->errors(),
                'csrf'    => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Tersimpan',
            'csrf'    => csrf_hash(),
        ]);
    }

    public function update($id)
    {
        $data = [
            'id'                 => (int)$id, // penting utk is_unique placeholder {id}
            'plat'               => $this->request->getPost('plat'),
            'nama'               => $this->request->getPost('nama'),
            'fuel_type'          => $this->request->getPost('fuel_type'),
            'kapasitas_tangki'   => $this->request->getPost('kapasitas_tangki'),
            'km_per_liter'       => $this->request->getPost('km_per_liter'),
            'stok_liter_terkini' => $this->request->getPost('stok_liter_terkini') ?: 0,
            'is_active'          => $this->request->getPost('is_active') ?? 1,
        ];
        $m = new VehicleModel();
        if (!$m->save($data)) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $m->errors(),
                'csrf'    => csrf_hash(),
            ]);
        }
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Diperbarui',
            'csrf'    => csrf_hash(),
        ]);
    }

    // Soft delete (label tombol: Hapus)
    public function delete($id)
    {
        $m = new \App\Models\VehicleModel();
        $m->delete((int)$id); // soft delete
        return $this->response->setJSON([
            'success'=>true, 'message'=>'Diarsipkan (soft delete).', 'csrf'=>csrf_hash()
        ]);
    }

    // Restore via POST /vehicles/{id}/restore
    public function restore($id)
    {
        $id = (int) $id;
        $m  = new VehicleModel();

        // pakai builder agar tidak terhalang allowedFields
        $db      = Database::connect();
        $builder = $db->table($m->table);

        // hanya pulihkan jika memang soft-deleted
        $builder->where('id', $id)
                ->where('deleted_at IS NOT NULL', null, false)
                ->set('deleted_at', null);

        $ok = $builder->update();
        $affected = $db->affectedRows();

        if (!$ok) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Gagal memulihkan data.',
                'csrf'    => csrf_hash(),
            ])->setStatusCode(500);
        }

        if ($affected < 1) {
            // tidak ada baris yang berubah: mungkin id tidak ada atau sudah aktif
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak ditemukan atau sudah aktif.',
                'csrf'    => csrf_hash(),
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Data berhasil dipulihkan.',
            'csrf'    => csrf_hash(),
        ]);
    }
}
