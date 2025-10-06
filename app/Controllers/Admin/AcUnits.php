<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcUnitModel; 

class AcUnits extends BaseController
{
    public function index()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $status  = $this->request->getGet('status');
        $perPage = (int)($this->request->getGet('perPage') ?? 10);
        $perPage = max($perPage, 10);

        $model = new AcUnitModel();
        $builder = $model->orderBy('id','DESC');
        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('kode_qr', $q)
                ->orLike('nomor_unik', $q)
                ->orLike('tipe_model', $q)
                ->orLike('lokasi', $q)
            ->groupEnd();
        }
        if ($status && in_array($status, ['NORMAL','MENUNGGU_PERBAIKAN','DALAM_PERBAIKAN'])) {
            $builder = $builder->where('status_ac', $status);
        }

        $rows = $builder->paginate($perPage);

        // hitung KPI ringkas (pakai countWhere sederhana biar cepat)
        $countTotal     = $model->countAllResults(false); // false biar tidak reset builder
        $countNormal    = (clone $model)->where('status_ac','NORMAL')->countAllResults();
        $countWait      = (clone $model)->where('status_ac','MENUNGGU_PERBAIKAN')->countAllResults();
        $countInProgress= (clone $model)->where('status_ac','DALAM_PERBAIKAN')->countAllResults();

        return view('Admin/ac_units/index', [
            'title'          => 'Master Data AC',
            'activeMenu'     => 'ac-units',
            'q'              => $q,
            'status'         => $status,
            'perPage'        => $perPage,
            'rows'           => $rows,
            'pager'          => $model->pager,
            'countTotal'     => $countTotal,
            'countNormal'    => $countNormal,
            'countWait'      => $countWait,
            'countInProgress'=> $countInProgress,
        ]);
    }

    public function show($id)
    {
        $row = (new \App\Models\AcUnitModel())->find($id);
        if (!$row) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak ditemukan',
                'csrf'    => csrf_hash(),
            ])->setStatusCode(404);
        }
        return $this->response->setJSON([
            'success' => true,
            'data'    => $row,
            'csrf'    => csrf_hash(),
        ]);
    }

    public function store()
    {
        $model = new \App\Models\AcUnitModel();
        $data  = $this->request->getPost([
            'kode_qr','nomor_unik','tipe_model','kapasitas_btu',
            'lokasi','status_ac','catatan'
        ]);

        // rules + unique create
        $rules = $model->getValidationRules();
        $rules['kode_qr']    .= '|is_unique[ac_units.kode_qr]';
        $rules['nomor_unik'] .= '|is_unique[ac_units.nomor_unik]';

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success'=>false,
                'errors' =>$this->validator->getErrors(),
                'csrf'   =>csrf_hash(),
            ])->setStatusCode(422);
        }

        $model->insert($data);
        return $this->response->setJSON([
            'success'=>true,
            'message'=>'Unit AC ditambahkan',
            'csrf'   =>csrf_hash(),
        ]);
    }

    public function update($id)
    {
        $model = new \App\Models\AcUnitModel();
        if (! $model->find($id)) {
            return $this->response->setJSON([
                'success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }

        $data = $this->request->getPost([
            'kode_qr','nomor_unik','tipe_model','kapasitas_btu',
            'lokasi','status_ac','catatan'
        ]);

        $rules = $model->getValidationRules();
        // ignore id saat cek unique
        $rules['kode_qr']    .= '|is_unique[ac_units.kode_qr,id,{id}]';
        $rules['nomor_unik'] .= '|is_unique[ac_units.nomor_unik,id,{id}]';
        $rules = str_replace('{id}', (string)$id, $rules);

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success'=>false,'errors'=>$this->validator->getErrors(),'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }

        $model->update($id, $data);
        return $this->response->setJSON([
            'success'=>true,'message'=>'Data diperbarui','csrf'=>csrf_hash()
        ]);
    }

    public function delete($id)
    {
        $model = new \App\Models\AcUnitModel();
        if (! $model->find($id)) {
            return redirect()->to(site_url('ac-units'))->with('msg_error','Data tidak ditemukan.');
        }
        $model->delete($id);
        return redirect()->to(site_url('ac-units'))->with('msg_success','Unit AC dihapus.');
    }

}