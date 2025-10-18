<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\BidangModel;
use App\Models\Auth\UserModel;

class Employees extends BaseController
{
    public function index()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 10);
        $scope   = strtolower(trim($this->request->getGet('scope') ?? 'all')); // all|active|inactive|archived

        $emp    = new EmployeeModel();
        $bidang = new \App\Models\BidangModel();

        // Soft delete scope
        if ($scope === 'archived') {
            $emp = $emp->onlyDeleted();            // hanya arsip
        } else {
            // non-arsip (default), boleh disaring aktif/non-aktif
            if ($scope === 'active')   $emp = $emp->where('employees.is_active', 1);
            if ($scope === 'inactive') $emp = $emp->where('employees.is_active', 0);
        }

        // JOIN users untuk username
        $builder = $emp->select('employees.*, users.username')
                    ->join('users', 'users.employee_id = employees.id', 'left')
                    ->orderBy('employees.id', 'DESC');

        if ($q !== '') {
            $builder = $builder->groupStart()
                ->like('employees.kode_pegawai',$q)
                ->orLike('employees.nama',$q)
                ->orLike('employees.email',$q)
                ->orLike('employees.no_telp',$q)
                ->orLike('users.username',$q)
            ->groupEnd();
        }

        $rows        = $builder->paginate($perPage);
        $countTotal  = (new EmployeeModel())->countAllResults();                 // non-arsip total
        $countActive = (new EmployeeModel())->where('is_active',1)->countAllResults();

        $bidangOptions = $bidang->select('id, nama')->orderBy('nama','ASC')->findAll();

        return view('Admin/employees/index', [
            'title'         => 'Data Pegawai',
            'activeMenu'    => 'employees',
            'q'             => $q,
            'perPage'       => $perPage,
            'rows'          => $rows,
            'pager'         => $emp->pager,
            'countTotal'    => $countTotal,
            'countActive'   => $countActive,
            'bidangOptions' => $bidangOptions,
            'scope'         => $scope,
        ]);
    }


    public function show($id)
{
    $row = (new EmployeeModel())
        ->withDeleted()
        ->select('employees.*, users.username, bidang.nama AS bidang_nama')
        ->join('users', 'users.employee_id = employees.id', 'left')
        ->join('bidang', 'bidang.id = employees.bidang_id', 'left')
        ->where('employees.id', (int)$id)
        ->first();

    if (!$row) {
        return $this->response->setJSON([
            'success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()
        ])->setStatusCode(404);
    }

    // URL foto publik (fallback ke placeholder)
    $row['foto_url'] = base_url(!empty($row['foto'])
        ? $row['foto']
        : 'assets/img/avatar-placeholder.svg');

    return $this->response->setJSON([
        'success'=>true,'data'=>$row,'csrf'=>csrf_hash()
    ]);
}


    public function store()
    {
        $emp  = new EmployeeModel();

        $p = $this->request->getPost();
        $kode       = trim($p['kode_pegawai'] ?? '');
        $nama       = trim($p['nama'] ?? '');
        $email      = trim($p['email'] ?? '');
        $no_telp    = trim($p['no_telp'] ?? '');
        $bidang_id  = ($p['bidang_id'] ?? '') !== '' ? (int)$p['bidang_id'] : null;
        $is_active  = (int)($p['is_active'] ?? 1);

        // opsional users
        $username   = trim($p['username'] ?? '');
        $password   = (string)($p['password'] ?? '');
        $igUsername = trim($p['instagram_username'] ?? '');

        $rules = [
            'kode_pegawai' => 'required|max_length[32]|is_unique[employees.kode_pegawai]',
            'nama'         => 'required|max_length[120]',
            'email'        => 'permit_empty|valid_email|is_unique[employees.email]',
            'instagram_username' => 'permit_empty|max_length[64]|regex_match[/^[A-Za-z0-9._]*$/]',
        ];

        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success'=>false,
                'errors'=>$this->validator->getErrors(),
                'csrf'=>csrf_hash(),
            ])->setStatusCode(422);
        }

        // === Upload foto (opsional) ===
        $fotoRelPath = null;
        $file = $this->request->getFile('foto');
        if ($file && $file->isValid() && $file->getSize() > 0) {
            // Batas 1 MB, tipe jpg/jpeg/png
            if ($file->getSize() > 1024 * 1024) {
                return $this->response->setJSON([
                    'success'=>false,
                    'errors'=>['foto' => 'Ukuran foto maksimal 1 MB'],
                    'csrf'=>csrf_hash(),
                ])->setStatusCode(422);
            }
            $mime = $file->getMimeType();
            if (! in_array($mime, ['image/jpeg','image/jpg','image/png'])) {
                return $this->response->setJSON([
                    'success'=>false,
                    'errors'=>['foto'=>'Format harus JPG/PNG'],
                    'csrf'=>csrf_hash(),
                ])->setStatusCode(422);
            }

            $uploadDir = FCPATH . 'uploads/foto_user';
            if (! is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

            $newName = uniqid('emp_') . '.' . strtolower($file->getExtension() ?: 'jpg');
            $file->move($uploadDir, $newName);
            $fotoRelPath = 'uploads/foto_user/' . $newName; // simpan path relatif publik
        }

        // simpan employees
        $empId = $emp->insert([
            'kode_pegawai' => $kode,
            'nama'         => $nama,
            'email'        => ($email !== '') ? $email : null,
            'no_telp'      => ($no_telp !== '') ? $no_telp : null,
            'bidang_id'    => $bidang_id,
            'is_active'    => $is_active,
            'instagram_username' => ($igUsername !== '') ? $igUsername : null,
            'foto' => $fotoRelPath,
        ], true);

        // kalau input username, buat/tautkan users
        if ($username !== '') {
            $userModel = new UserModel();
            $passHash = password_hash(($password !== '' ? $password : '123'), PASSWORD_DEFAULT);

            $exists = $userModel->where('employee_id', $empId)->first();
            if ($exists) {
                $dataU = [
                    'username' => $username,
                    'name'     => $nama,
                    'active'   => $is_active,
                ];
                if ($password !== '') $dataU['password_hash'] = $passHash;
                $userModel->update($exists['id'], $dataU);
            } else {
                $userModel->insert([
                    'username'      => $username,
                    'password_hash' => $passHash,
                    'role'          => 'user',
                    'active'        => $is_active,
                    'name'          => $nama,
                    'employee_id'   => $empId,
                ]);
            }
        }

        return $this->response->setJSON([
            'success'=>true,'message'=>'Pegawai ditambahkan','csrf'=>csrf_hash()
        ]);
    }

    public function update($id)
    {
        $emp = new EmployeeModel();
        $row = $emp->withDeleted()->find((int)$id);
        if (!$row) {
            return $this->response->setJSON([
                'success'=>false,'message'=>'Data tidak ditemukan','csrf'=>csrf_hash()
            ])->setStatusCode(404);
        }
    
        $p = $this->request->getPost();
        $kode       = trim($p['kode_pegawai'] ?? '');
        $nama       = trim($p['nama'] ?? '');
        $email      = trim($p['email'] ?? '');
        $no_telp    = trim($p['no_telp'] ?? '');
        $bidang_id  = ($p['bidang_id'] ?? '') !== '' ? (int)$p['bidang_id'] : null;
        $is_active  = (int)($p['is_active'] ?? 1);
        $username   = trim($p['username'] ?? '');
        $password   = (string)($p['password'] ?? '');
        $igUsername = trim($p['instagram_username'] ?? '');
    
        $rules = [
            'kode_pegawai'        => 'required|max_length[32]|is_unique[employees.kode_pegawai,id,{id}]',
            'nama'                => 'required|max_length[120]',
            'email'               => 'permit_empty|valid_email|is_unique[employees.email,id,{id}]',
            'instagram_username'  => 'permit_empty|max_length[64]|regex_match[/^[A-Za-z0-9._]*$/]',
            // foto divalidasi manual
        ];
        $rules = str_replace('{id}', (string)$id, $rules);
    
        if (! $this->validate($rules)) {
            return $this->response->setJSON([
                'success'=>false,'errors'=>$this->validator->getErrors(),'csrf'=>csrf_hash()
            ])->setStatusCode(422);
        }
    
        $dataUpd = [
            'kode_pegawai'       => $kode,
            'nama'               => $nama,
            'email'              => ($email !== '') ? $email : null,
            'no_telp'            => ($no_telp !== '') ? $no_telp : null,
            'bidang_id'          => $bidang_id,
            'is_active'          => $is_active,
            'instagram_username' => ($igUsername !== '') ? $igUsername : null,
        ];
    
        // Upload foto baru (opsional)
        $file = $this->request->getFile('foto');
        if ($file && $file->isValid() && $file->getSize() > 0) {
            if ($file->getSize() > 1024 * 1024) {
                return $this->response->setJSON([
                    'success'=>false,
                    'errors'=>['foto' => 'Ukuran foto maksimal 1 MB'],
                    'csrf'=>csrf_hash(),
                ])->setStatusCode(422);
            }
            $mime = $file->getMimeType();
            if (! in_array($mime, ['image/jpeg','image/jpg','image/png'])) {
                return $this->response->setJSON([
                    'success'=>false,
                    'errors'=>['foto'=>'Format harus JPG/PNG'],
                    'csrf'=>csrf_hash(),
                ])->setStatusCode(422);
            }
    
            $uploadDir = FCPATH . 'uploads/foto_user';
            if (! is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);
    
            $newName = uniqid('emp_') . '.' . strtolower($file->getExtension() ?: 'jpg');
            $file->move($uploadDir, $newName);
            $fotoRelPath = 'uploads/foto_user/' . $newName;
    
            // hapus file lama (jika ada)
            if (!empty($row['foto'])) {
                $oldPath = FCPATH . $row['foto'];
                if (is_file($oldPath)) @unlink($oldPath);
            }
    
            $dataUpd['foto'] = $fotoRelPath;
        }
    
        $emp->update((int)$id, $dataUpd);
    
        // Kelola akun users terkait (opsional)
        $userModel = new \App\Models\Auth\UserModel();
        $user      = $userModel->where('employee_id', (int)$id)->first();
    
        if ($username !== '') {
            $dataU = [
                'username' => $username,
                'name'     => $nama,
                'active'   => $is_active,
            ];
            if ($password !== '') $dataU['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    
            if ($user) {
                $userModel->update($user['id'], $dataU);
            } else {
                $dataU += [
                    'password_hash' => password_hash(($password !== '' ? $password : '123'), PASSWORD_DEFAULT),
                    'role'          => 'user',
                    'employee_id'   => (int)$id,
                ];
                $userModel->insert($dataU);
            }
        }
    
        return $this->response->setJSON(['success'=>true,'message'=>'Pegawai diperbarui','csrf'=>csrf_hash()]);
    }
    

    public function delete($id = null)
    {
        $id = (int) $id;
        if (!$id) return redirect()->back()->with('error', 'ID tidak valid');

        $emp = new EmployeeModel();
        if (!$emp->find($id)) return redirect()->back()->with('error', 'Data tidak ditemukan');

        $emp->delete($id); // soft delete
        return redirect()->to(site_url('admin/pegawai'))->with('success', 'Data diarsipkan.');
    }

    public function restore($id)
    {
        $id = (int) $id;

        $m = new EmployeeModel();
        $row = $m->onlyDeleted()->find($id);
        if (! $row) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data tidak ditemukan atau tidak dalam arsip',
                'csrf'    => csrf_hash(),
            ])->setStatusCode(404);
        }

        $ok = $m->protect(false)->update($id, ['deleted_at' => null]);
        if (! $ok) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Gagal memulihkan data',
                'csrf'    => csrf_hash(),
            ])->setStatusCode(500);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Data dipulihkan',
            'csrf'    => csrf_hash(),
        ]);
    }

    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $scope   = strtolower(trim($this->request->getGet('scope') ?? 'all')); // all|active|inactive|archived
        $offset  = ($page - 1) * $perPage;

        $emp = new EmployeeModel();
        $db  = \Config\Database::connect();

        // Soft delete scope + aktif/non-aktif
        if ($scope === 'archived') {
            $emp = $emp->onlyDeleted(); // hanya arsip
        } else {
            // non-arsip
            if ($scope === 'active')   $emp = $emp->where('employees.is_active', 1);
            if ($scope === 'inactive') $emp = $emp->where('employees.is_active', 0);
        }

        // JOIN users agar ada username
        $base = $emp->select('employees.id, employees.kode_pegawai, employees.nama, users.username, employees.is_active')
                    ->join('users', 'users.employee_id = employees.id', 'left');

        if ($q !== '') {
            $exact  = $db->escape($q);
            $starts = $db->escape($q . '%');
            $like   = $db->escape('%' . $q . '%');

            $base = $base->groupStart()
                ->like('employees.kode_pegawai', $q)
                ->orLike('employees.nama', $q)
                ->orLike('users.username', $q)
            ->groupEnd();

            $scoreSql = "
            ((employees.kode_pegawai = {$exact}) * 120) +
            ((employees.nama         = {$exact}) * 110) +
            ((users.username         = {$exact}) * 105) +
            ((employees.kode_pegawai LIKE {$starts}) * 70) +
            ((employees.nama         LIKE {$starts}) * 60) +
            ((users.username         LIKE {$starts}) * 55) +
            ((employees.kode_pegawai LIKE {$like})   * 20) +
            ((employees.nama         LIKE {$like})   * 18) +
            ((users.username         LIKE {$like})   * 16)
            AS score";

            $base = $base->select($scoreSql, false)
                        ->orderBy('score', 'DESC')
                        ->orderBy('employees.nama', 'ASC')
                        ->orderBy('employees.id', 'DESC');

            $total = (clone $base)->select('employees.id')->orderBy('', '', false)->countAllResults(false);
        } else {
            $base  = $base->orderBy('employees.id', 'DESC');
            $total = (clone $base)->select('employees.id')->orderBy('', '', false)->countAllResults(false);
        }

        $rows = $base->limit($perPage, $offset)->get()->getResultArray();

        $data = array_map(static function($r){
            return [
                'id'           => (int)$r['id'],
                'kode_pegawai' => (string)$r['kode_pegawai'],
                'nama'         => (string)$r['nama'],
                'username'     => (string)($r['username'] ?? ''),
                'is_active'    => (int)$r['is_active'],
                'delete_url'   => site_url('admin/pegawai/'.$r['id']),
                'foto_url'     => base_url(!empty($r['foto']) ? $r['foto'] : 'assets/img/avatar-placeholder.svg'),
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
            'csrf'       => csrf_hash(),
        ]);
    }


    public function export()
    {
        $q = trim($this->request->getGet('q') ?? '');

        // default NON-arsip
        $emp = (new EmployeeModel())
            ->select('employees.id, employees.kode_pegawai, employees.nama, users.username, employees.email, employees.no_telp, employees.bidang_id, employees.is_active, employees.created_at, employees.updated_at, employees.deleted_at')
            ->join('users', 'users.employee_id = employees.id', 'left');

        if ($q !== '') {
            $emp = $emp->groupStart()
                ->like('employees.kode_pegawai', $q)
                ->orLike('employees.nama', $q)
                ->orLike('users.username', $q)
            ->groupEnd();
        }

        $rows = $emp->orderBy('employees.id','ASC')->findAll();

        $headers = [
            'No','ID','Kode','Nama','Username','Email','No. Telp',
            'Bidang ID','Aktif','Dihapus?','Dibuat','Diubah'
        ];

        $fp = fopen('php://temp', 'w+');
        fwrite($fp, "\xEF\xBB\xBF"); // BOM
        fputcsv($fp, $headers);

        $no = 1;
        foreach ($rows as $r) {
            fputcsv($fp, [
                $no++,
                (int)($r['id'] ?? 0),
                (string)($r['kode_pegawai'] ?? ''),
                (string)($r['nama'] ?? ''),
                (string)($r['username'] ?? ''),
                (string)($r['email'] ?? ''),
                (string)($r['no_telp'] ?? ''),
                ($r['bidang_id'] === null ? '' : (int)$r['bidang_id']),
                ((int)($r['is_active'] ?? 0) === 1) ? 'Ya' : 'Tidak',
                (!empty($r['deleted_at'])) ? 'Ya' : 'Tidak',
                (string)($r['created_at'] ?? ''),
                (string)($r['updated_at'] ?? ''),
            ]);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        $fname = 'pegawai_' . date('Ymd_His') . '.csv';

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="'.$fname.'"')
            ->setBody($csv);
    }
}
