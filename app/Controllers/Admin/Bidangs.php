<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\BidangModel;
use App\Models\EmployeeModel;

class Bidangs extends BaseController
{
    public function index()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 10);

        $m = new BidangModel();

        $builder = $m->orderBy('id','DESC');
        if ($q !== '') {
            $builder = $builder->like('nama', $q);
        }

        $rows = $builder->paginate($perPage);

        return view('Admin/bidang/index', [
            'title'      => 'Departemen',
            'activeMenu' => 'bidang',
            'q'          => $q,
            'perPage'    => $perPage,
            'rows'       => $rows,
            'pager'      => $m->pager,
            'countTotal' => (new BidangModel())->countAllResults(),
        ]);
    }

    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $m = new BidangModel();
        $base = $m->select('id, nama')->orderBy('id','DESC');

        if ($q !== '') {
            $base = $base->like('nama', $q);
        }

        $total = (clone $base)->select('id')->orderBy('', '', false)->countAllResults(false);
        $rows  = $base->limit($perPage, $offset)->get()->getResultArray();

        return $this->response->setJSON([
            'success'   => true,
            'q'         => $q,
            'total'     => (int)$total,
            'perPage'   => (int)$perPage,
            'page'      => (int)$page,
            'pageCount' => (int)ceil(max($total,1)/$perPage),
            'rows'      => array_map(fn($r)=>['id'=>(int)$r['id'],'nama'=>(string)$r['nama']], $rows),
            'csrf'      => csrf_hash(),
        ]);
    }

    public function show($id)
    {
        $row = (new BidangModel())->find((int)$id);
        if (!$row) {
            return $this->response->setJSON([
                'success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }

        return $this->response->setJSON(['success'=>true,'data'=>$row,'csrf'=>csrf_hash()]);
    }

    public function store()
    {
        $m    = new \App\Models\BidangModel();
        $post = $this->request->getPost(['nama']);
        $rules = $m->getValidationRules();

        if (! $this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false,
                    'errors'  => $this->validator->getErrors(),
                    'csrf'    => csrf_hash(),
                ])->setStatusCode(422);
            }
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $m->insert(['nama' => trim($post['nama'] ?? '')]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Bidang ditambahkan',
                'csrf'    => csrf_hash(),
            ]);
        }

        return redirect()->to(site_url('admin/bidang'))
            ->with('success', 'Bidang ditambahkan');
    }

    public function update($id)
    {
        $m = new \App\Models\BidangModel();
        if (! $m->find((int)$id)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()
                ])->setStatusCode(404);
            }
            return redirect()->to(site_url('admin/bidang'))->with('error','Data tidak ditemukan');
        }
    
        $post  = $this->request->getPost(['nama']);
        $rules = $m->getValidationRules();
        $rules['nama'] = str_replace('{id}', (string)$id, $rules['nama']);
    
        if (! $this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success'=>false,'errors'=>$this->validator->getErrors(),'csrf'=>csrf_hash()
                ])->setStatusCode(422);
            }
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }
    
        $m->update((int)$id, ['nama' => trim($post['nama'] ?? '')]);
    
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success'=>true,'message'=>'Bidang diperbarui','csrf'=>csrf_hash()
            ]);
        }
    
        return redirect()->to(site_url('admin/bidang'))->with('success','Bidang diperbarui');
    }
        
    public function delete($id)
    {
        $id = (int)$id;

        // Lindungi jika masih dipakai di employees
        $used = (new EmployeeModel())->where('bidang_id', $id)->countAllResults();
        if ($used > 0) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus: masih dipakai di data pegawai.');
        }

        $m = new BidangModel();
        if (! $m->find($id)) {
            return redirect()->back()->with('error', 'Data tidak ditemukan.');
        }

        $m->delete($id); // hard delete
        return redirect()->to(site_url('admin/bidang'))->with('success', 'Data dihapus.');
    }
}
