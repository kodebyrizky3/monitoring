<?php
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\EmployeeModel;

class Employees extends BaseController
{
    public function index()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = (int)($this->request->getGet('perPage') ?? 10);
        $perPage = max($perPage, 10);

        $model = new EmployeeModel();
        $builder = $model->orderBy('id','DESC');
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('kode_pegawai',$q)
                ->orLike('nama',$q)
                ->orLike('email',$q)
                ->orLike('no_telp',$q)
            ->groupEnd();
        }

        $rows = $builder->paginate($perPage);
        $countTotal  = $model->countAllResults(false);
        $countActive = (clone $model)->where('is_active',1)->countAllResults();

        return view('Admin/employees/index', [
            'title'       => 'Data Pegawai',
            'activeMenu'  => 'employees',
            'q'           => $q,
            'perPage'     => $perPage,
            'rows'        => $rows,
            'pager'       => $model->pager,
            'countTotal'  => $countTotal,
            'countActive' => $countActive,
        ]);
    }

    // ===== JSON endpoints untuk modal =====
    public function show($id)
    {
        $row = (new EmployeeModel())->find($id);
        if (!$row) return $this->response->setJSON(['success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()])->setStatusCode(404);
        return $this->response->setJSON(['success'=>true,'data'=>$row,'csrf'=>csrf_hash()]);
    }

    public function store()
    {
        $model = new EmployeeModel();
        $data  = $this->request->getPost(['kode_pegawai','nama','email','no_telp','is_active']);

        $rules = $model->getValidationRules();
        // unik saat create
        $rules['kode_pegawai'] .= '|is_unique[employees.kode_pegawai]';
        if (!empty($data['email'])) $rules['email'] .= '|is_unique[employees.email]';

        if (! $this->validate($rules)) {
            return $this->response->setJSON(['success'=>false,'errors'=>$this->validator->getErrors(),'csrf'=>csrf_hash()])->setStatusCode(422);
        }

        $data['is_active'] = (int)($data['is_active'] ?? 1);
        $model->insert($data);
        return $this->response->setJSON(['success'=>true,'message'=>'Pegawai ditambahkan','csrf'=>csrf_hash()]);
    }

    public function update($id)
    {
        $model = new EmployeeModel();
        if (! $model->find($id)) return $this->response->setJSON(['success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()])->setStatusCode(404);

        $data  = $this->request->getPost(['kode_pegawai','nama','email','no_telp','is_active']);
        $rules = $model->getValidationRules();
        // unik dengan ignore row
        $rules['kode_pegawai'] .= '|is_unique[employees.kode_pegawai,id,{id}]';
        $rules = str_replace('{id}', (string)$id, $rules);
        if (!empty($data['email'])) $rules['email'] .= '|is_unique[employees.email,id,'.(int)$id.']';

        if (! $this->validate($rules)) {
            return $this->response->setJSON(['success'=>false,'errors'=>$this->validator->getErrors(),'csrf'=>csrf_hash()])->setStatusCode(422);
        }

        $data['is_active'] = (int)($data['is_active'] ?? 1);
        $model->update($id,$data);
        return $this->response->setJSON(['success'=>true,'message'=>'Pegawai diperbarui','csrf'=>csrf_hash()]);
    }

    public function delete($id = null)
    {
        $id = (int) $id;
        if (!$id) return redirect()->back()->with('error', 'ID tidak valid');

        $model = new EmployeeModel();
        if (!$model->find($id)) {
            return redirect()->back()->with('error', 'Data tidak ditemukan');
        }

        $model->delete($id);

        // Pesan generik tanpa nama/ID
        return redirect()->to(site_url('pegawai'))
            ->with('success', 'Data telah dihapus.');
    }


    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;
    
        $model = new EmployeeModel();
        $db    = \Config\Database::connect();
    
        $base = $model->select([
            'id','kode_pegawai','nama','email','no_telp','is_active'
        ]);
    
        if ($q !== '') {
            // siapkan pattern
            $exact  = $db->escape($q);
            $starts = $db->escape($q . '%');
            $like   = $db->escape('%' . $q . '%');
    
            // filter WHERE (menggunakan OR group)
            $base = $base->groupStart()
                ->like('kode_pegawai', $q)
                ->orLike('nama', $q)
                ->orLike('email', $q)
                ->orLike('no_telp', $q)
            ->groupEnd();
    
            // kolom skor relevansi (lebih tinggi untuk exact/prefix)
            $scoreSql = "
              ((kode_pegawai = {$exact}) * 120) +
              ((nama         = {$exact}) * 110) +
              ((kode_pegawai LIKE {$starts}) * 70) +
              ((nama         LIKE {$starts}) * 60) +
              ((email        LIKE {$starts}) * 40) +
              ((kode_pegawai LIKE {$like})   * 20) +
              ((nama         LIKE {$like})   * 18) +
              ((email        LIKE {$like})   * 10) +
              ((no_telp      LIKE {$like})   * 8)
            AS score";
    
            $base = $base->select($scoreSql, false)
                         ->orderBy('score', 'DESC')
                         ->orderBy('nama', 'ASC')   // tie-breaker enak dibaca
                         ->orderBy('id', 'DESC');   // terakhir
            // hitung total
            $countBuilder = clone $base;
            // hapus limit/order untuk countAllResults
            $total = $countBuilder->select('id')->orderBy('', '', false)->countAllResults(false);
        } else {
            // tanpa q → pakai default order
            $base  = $base->orderBy('id', 'DESC');
            $total = (clone $base)->select('id')->orderBy('', '', false)->countAllResults(false);
        }
    
        // ambil halaman
        $rows = $base->limit($perPage, $offset)->get()->getResultArray();
    
        $data = array_map(static function($r){
            return [
                'id'           => (int)$r['id'],
                'kode_pegawai' => (string)$r['kode_pegawai'],
                'nama'         => (string)$r['nama'],
                'email'        => (string)($r['email'] ?? ''),
                'no_telp'      => (string)($r['no_telp'] ?? ''),
                'is_active'    => (int)$r['is_active'],
                'delete_url'   => site_url('pegawai/'.$r['id']),
            ];
        }, $rows ?? []);
    
        return $this->response->setJSON([
            'success'    => true,
            'q'          => $q,
            'total'      => (int)$total,
            'perPage'    => (int)$perPage,
            'page'       => (int)$page,
            'pageCount'  => (int)ceil(max($total,1)/$perPage),
            'rows'       => $data,
        ]);
    }
    
}
