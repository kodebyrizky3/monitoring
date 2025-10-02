<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcUnitModel;
use App\Models\AcRepairModel;
use App\Models\AcTicketModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

class AcUnits extends BaseController
{
    /* LIST (server-render pertama + counters awal) */
    public function index()
    {
        $AC   = new AcUnitModel();
        $rows = $AC->orderBy('id','DESC')->findAll();

        // counters global (tanpa filter)
        $db = \Config\Database::connect();
        $cnt = $db->table('ac_units')
            ->select("
                COUNT(*) AS total,
                SUM(CASE WHEN status_ac='MENUNGGU_PERBAIKAN' THEN 1 ELSE 0 END) AS wait_cnt,
                SUM(CASE WHEN status_ac='DALAM_PERBAIKAN'   THEN 1 ELSE 0 END) AS doing_cnt,
                SUM(CASE WHEN status_ac='NORMAL'            THEN 1 ELSE 0 END) AS normal_cnt
            ", false)
            ->get()->getRowArray() ?? ['total'=>0,'wait_cnt'=>0,'doing_cnt'=>0,'normal_cnt'=>0];

        return view('Admin/ac_units/index', [
            'title'        => 'Data Alat · AC',
            'activeMenu'   => 'ac.list',
            'rows'         => $rows,
            'countTotal'   => (int)$cnt['total'],
            'countWait'    => (int)$cnt['wait_cnt'],
            'countDoing'   => (int)$cnt['doing_cnt'],
            'countNormal'  => (int)$cnt['normal_cnt'],
            // untuk SweetAlert dari flash
            'flashOk'      => session()->getFlashdata('ok'),
            'flashErr'     => session()->getFlashdata('err'),
        ]);
    }

    /* SEARCH (JSON untuk tabel/pager + update cards) */
    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $status  = trim($this->request->getGet('status') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $db  = \Config\Database::connect();
        $mdl = new AcUnitModel();

        // base builder untuk TABLE (q + status)
        $base = $mdl->select('id,kode_qr,nomor_unik,tipe_model,lokasi,status_ac')
                    ->orderBy('id','DESC');

        if ($q !== '') {
            $base = $base->groupStart()
                ->like('kode_qr', $q)
                ->orLike('nomor_unik', $q)
                ->orLike('tipe_model', $q)
                ->orLike('lokasi', $q)
            ->groupEnd();
        }
        if ($status !== '') {
            $base = $base->where('status_ac', $status);
        }

        // total berdasarkan q + status
        $total = (clone $base)->select('id')->orderBy('', '', false)->countAllResults(false);

        // ambil halaman
        $rows = $base->limit($perPage, $offset)->get()->getResultArray();

        // map rows untuk front-end
        $data = array_map(static function($r){
            $id  = (int)$r['id'];
            return [
                'id'        => $id,
                'kode_qr'   => (string)($r['kode_qr'] ?? ''),
                'nomor_unik'=> (string)($r['nomor_unik'] ?? ''),
                'tipe_model'=> (string)($r['tipe_model'] ?? ''),
                'lokasi'    => (string)($r['lokasi'] ?? ''),
                'status_ac' => (string)($r['status_ac'] ?? ''),
                'show_url'  => site_url('admin/data-alat/ac/'.$id),
                'edit_url'  => site_url('admin/data-alat/ac/'.$id.'/edit'),
                'dl_qr_url' => site_url('admin/data-alat/ac/'.$id.'/qr/download'),
                'del_url'   => site_url('admin/data-alat/ac/'.$id.'/delete'),
            ];
        }, $rows ?? []);

        // counters untuk cards: hanya q (abaikan status)
        $counterQB = $db->table('ac_units');
        if ($q !== '') {
            $counterQB = $counterQB->groupStart()
                ->like('kode_qr', $q)
                ->orLike('nomor_unik', $q)
                ->orLike('tipe_model', $q)
                ->orLike('lokasi', $q)
            ->groupEnd();
        }
        $counters = $counterQB->select("
            COUNT(*) AS total,
            SUM(CASE WHEN status_ac='MENUNGGU_PERBAIKAN' THEN 1 ELSE 0 END) AS wait_cnt,
            SUM(CASE WHEN status_ac='DALAM_PERBAIKAN'   THEN 1 ELSE 0 END) AS doing_cnt,
            SUM(CASE WHEN status_ac='NORMAL'            THEN 1 ELSE 0 END) AS normal_cnt
        ", false)->get()->getRowArray() ?? ['total'=>0,'wait_cnt'=>0,'doing_cnt'=>0,'normal_cnt'=>0];

        return $this->response->setJSON([
            'success'   => true,
            'q'         => $q,
            'status'    => $status,
            'total'     => (int)$total,
            'perPage'   => (int)$perPage,
            'page'      => (int)$page,
            'pageCount' => (int)ceil(max($total,1)/$perPage),
            'rows'      => $data,
            'counters'  => [
                'total'  => (int)$counters['total'],
                'wait'   => (int)$counters['wait_cnt'],
                'doing'  => (int)$counters['doing_cnt'],
                'normal' => (int)$counters['normal_cnt'],
            ],
        ]);
    }

    /* DETAIL */
    public function show(int $id)
    {
        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');

        [$merek,$model] = $this->splitBrandModel($row['tipe_model'] ?? '');
        $sn = $this->extractSn($row['catatan'] ?? null);

        $Rep     = new AcRepairModel();
        $repairs = $Rep->listByAc($id);

        return view('Admin/ac_units/show', [
            'title'      => 'Detail Alat AC',
            'activeMenu' => 'ac.list',
            'row'        => $row,
            'merek'      => $merek,
            'model'      => $model,
            'sn'         => $sn,
            'repairs'    => $repairs,
        ]);
    }

    /* EDIT FORM */
    public function edit(int $id)
    {
        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');

        [$merek,$model] = $this->splitBrandModel($row['tipe_model'] ?? '');
        $sn = $this->extractSn($row['catatan'] ?? null);

        return view('Admin/ac_units/edit', [
            'title'      => 'Edit Alat AC',
            'activeMenu' => 'ac.list',
            'row'        => $row,
            'merek'      => $merek,
            'model'      => $model,
            'sn'         => $sn,
        ]);
    }

    /* UPDATE (tanpa mengubah kode_qr) */
    public function update(int $id)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return redirect()->back()->with('err','Method Not Allowed');
        }

        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');

        $nama   = trim((string)$this->request->getPost('nomor_unik'));
        $merek  = trim((string)$this->request->getPost('merek'));
        $model  = trim((string)$this->request->getPost('model'));
        $sn     = trim((string)$this->request->getPost('sn'));
        $lokasi = trim((string)$this->request->getPost('lokasi'));
        $status = strtoupper(trim((string)$this->request->getPost('status_ac') ?: 'NORMAL'));

        $data = [
            'id'         => $id,
            'nomor_unik' => $nama ?: $row['nomor_unik'],
            'tipe_model' => $this->buildBrandModel($merek,$model) ?: ($row['tipe_model'] ?? '-'),
            'lokasi'     => $lokasi ?: ($row['lokasi'] ?? '-'),
            'status_ac'  => in_array($status,['NORMAL','MENUNGGU_PERBAIKAN','DALAM_PERBAIKAN'],true) ? $status : ($row['status_ac'] ?? 'NORMAL'),
            'catatan'    => $this->upsertSn($row['catatan'] ?? null, $sn),
        ];

        try {
            if (!$AC->save($data)) {
                return redirect()->back()->withInput()->with('err','Validasi gagal: '.json_encode($AC->errors()));
            }
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('err','DB error: '.$e->getMessage());
        }

        return redirect()->route('admin.ac.show',[$id])->with('ok','Data berhasil diperbarui');
    }

    /* DELETE (hapus relasi + folder foto + file QR) */
    public function delete(int $id)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return redirect()->back()->with('err','Method Not Allowed');
        }

        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) {
            return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');
        }

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            // Hapus anak (jika FK tidak cascade)
            (new AcRepairModel())->where('ac_id',$id)->delete();
            (new AcTicketModel())->where('ac_id',$id)->delete();

            // Hapus parent
            $db->table('ac_units')->where('id',$id)->delete();

            // Hapus folder foto perangkat
            $dirPhotos = FCPATH.'uploads/ac_units/'.$id;
            $this->rrmdir($dirPhotos);

            // Hapus file QR
            $token = trim((string)($row['kode_qr'] ?? ''));
            if ($token !== '') {
                $qrFile = FCPATH.'uploads/qrcodes/'.$token.'.png';
                if (is_file($qrFile)) @unlink($qrFile);
            }

            $db->transCommit();
            return redirect()->route('admin.ac.index')->with('ok','Data & file terkait berhasil dihapus');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->route('admin.ac.show',[$id])->with('err','Gagal hapus: '.$e->getMessage());
        }
    }

    /* DOWNLOAD ULANG QR (PNG) – simpan di uploads/qrcodes */
    public function downloadQr(int $id)
    {
        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row || empty($row['kode_qr'])) {
            return redirect()->route('admin.ac.index')->with('err','Data/QR tidak ditemukan.');
        }

        $token    = $row['kode_qr'];
        $dataUrl  = site_url('ac/'.rawurlencode($token));
        $dir      = FCPATH.'uploads/qrcodes';
        $diskFile = $dir.'/'.$token.'.png';

        if (!is_dir($dir)) @mkdir($dir, 0775, true);

        if (!is_file($diskFile)) {
            // generate dari API publik (online)
            $api = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&qzone=2&data='.rawurlencode($dataUrl);
            $png = @file_get_contents($api);
            if ($png === false) {
                return redirect()->route('admin.ac.show', [$id])->with('err','Gagal generate QR.');
            }
            @file_put_contents($diskFile, $png);
        }

        $safeName = $this->slugify(($row['nomor_unik'] ?? 'ac').'-qr').'.png';
        return $this->response->download($diskFile, null)->setFileName($safeName);
    }

    /* ===== Helpers ===== */
    private function splitBrandModel(string $tipe): array
    {
        $t = trim($tipe);
        if ($t==='') return ['',''];
        $parts = preg_split('/\s+/', $t, 2);
        return [strtoupper($parts[0]??''), trim($parts[1]??'')];
    }
    private function buildBrandModel(string $merek,string $model): string
    {
        $m = trim($merek); $d = trim($model);
        if ($m==='' && $d==='') return '';
        if ($d==='') return strtoupper($m);
        if ($m==='') return $d;
        return strtoupper($m).' '.$d;
    }
    private function extractSn(?string $catatan): string
    {
        if (!$catatan) return '';
        if (preg_match('/\bSN\s*=\s*([^\r\n]+)/i',$catatan,$m)) return trim($m[1]);
        return '';
    }
    private function upsertSn(?string $catatan,string $sn): ?string
    {
        $sn = trim($sn);
        $cat = $catatan ? trim($catatan) : '';
        if ($sn==='') return $cat ?: null;
        if ($cat==='') return "SN=".$sn;
        if (preg_match('/\bSN\s*=\s*[^\r\n]+/i',$cat)) return preg_replace('/\bSN\s*=\s*[^\r\n]+/i','SN='.$sn,$cat);
        return rtrim($cat)."\nSN=".$sn;
    }
    private function slugify(string $text): string
    {
        $text = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT', $text) : $text;
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = preg_replace('~[^-\\w]+~', '', $text);
        $text = strtolower($text);
        return $text ?: 'qr';
    }
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $fl = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($fl as $f) {
            $p = $f->getRealPath();
            if ($f->isDir()) { @rmdir($p); } else { @unlink($p); }
        }
        @rmdir($dir);
    }
}
