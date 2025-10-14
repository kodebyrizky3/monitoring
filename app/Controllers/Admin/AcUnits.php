<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcUnitModel;
use App\Models\AcRepairModel;
use App\Models\AcTicketModel;
use CodeIgniter\Database\Exceptions\DatabaseException;

class AcUnits extends BaseController
{
    private const ALLOWED_STATUS = ['NORMAL','RUSAK_RINGAN','RUSAK_BERAT'];

    /* LIST (render awal + angka kartu) */
    public function index()
    {
        $AC   = new AcUnitModel();
        $list = $AC->select('id,kode_qr,nomor_unik,tipe_model,kapasitas_btu,bmn_no_display,lokasi,status_ac')
                   ->orderBy('id','DESC')->findAll(10);

        return view('Admin/ac_units/index', [
            'title'      => 'Data Alat · AC',
            'activeMenu' => 'ac.list',
            'rows'       => $list,
            'stats'      => $this->calcStats(),
        ]);
    }

    /* ====== ENDPOINT SEARCH (JSON) untuk tabel + refresh kartu ====== */
    public function search()
    {
        $q       = trim($this->request->getGet('q') ?? '');
        $status  = trim($this->request->getGet('status') ?? '');
        $perPage = max((int)($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int)($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $m = new AcUnitModel();
        $b = $m->select('id,kode_qr,nomor_unik,tipe_model,kapasitas_btu,bmn_no_display,lokasi,status_ac')
               ->orderBy('id','DESC');

        if ($q !== '') {
            $b = $b->groupStart()
                    ->like('kode_qr', $q)
                    ->orLike('nomor_unik', $q)
                    ->orLike('tipe_model', $q)
                    ->orLike('kapasitas_btu', $q)
                    ->orLike('bmn_no_display', $q)
                    ->orLike('lokasi', $q)
                 ->groupEnd();
        }
        if ($status !== '' && in_array($status, self::ALLOWED_STATUS, true)) {
            $b = $b->where('status_ac', $status);
        }

        // total
        $countB = clone $b;
        $total  = $countB->select('id')->countAllResults(false);

        // page data
        $rows = $b->limit($perPage, $offset)->get()->getResultArray();

        // siapkan URL aksi buat tabel
        $data = array_map(static function(array $r){
            $id = (int)$r['id'];
            return [
                'id'             => $id,
                'kode_qr'        => (string)($r['kode_qr'] ?? ''),
                'nomor_unik'     => (string)($r['nomor_unik'] ?? ''),
                'tipe_model'     => (string)($r['tipe_model'] ?? ''),
                'kapasitas_btu'  => (string)($r['kapasitas_btu'] ?? ''),
                'bmn_no_display' => (string)($r['bmn_no_display'] ?? ''),
                'lokasi'         => (string)($r['lokasi'] ?? ''),
                'status_ac'      => (string)($r['status_ac'] ?? ''),
                'show_url'       => route_to('admin.ac.show', $id),
                'edit_url'       => route_to('admin.ac.edit', $id),
                'dl_qr_url'      => route_to('admin.ac.qr.download', $id),
                'del_url'        => route_to('admin.ac.delete', $id),
            ];
        }, $rows ?? []);

        return $this->response->setJSON([
            'success'   => true,
            'q'         => $q,
            'total'     => (int)$total,
            'perPage'   => (int)$perPage,
            'page'      => (int)$page,
            'pageCount' => (int)ceil(max($total,1)/$perPage),
            'rows'      => $data,
            'stats'     => $this->calcStats(),
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
            'photoUrl'   => $this->findPhotoUrl($id),
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
            'photoUrl'   => $this->findPhotoUrl($id),
        ]);
    }

    /* UPDATE (terima BTU & BMN; validasi status baru) */
    public function update(int $id)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return redirect()->back()->with('err','Method Not Allowed');
        }

        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');

        $nama    = trim((string)$this->request->getPost('nomor_unik'));
        $merek   = trim((string)$this->request->getPost('merek'));
        $model   = trim((string)$this->request->getPost('model'));
        $sn      = trim((string)$this->request->getPost('sn'));
        $lokasi  = trim((string)$this->request->getPost('lokasi'));
        $btu     = trim((string)$this->request->getPost('kapasitas_btu'));
        $bmn     = trim((string)$this->request->getPost('bmn_no_display'));
        $status  = strtoupper(trim((string)$this->request->getPost('status_ac') ?: 'NORMAL'));

        $statusFinal = in_array($status, self::ALLOWED_STATUS, true) ? $status : ($row['status_ac'] ?? 'NORMAL');

        $data = [
            'id'             => $id,
            'nomor_unik'     => $nama ?: $row['nomor_unik'],
            'tipe_model'     => $this->buildBrandModel($merek,$model) ?: ($row['tipe_model'] ?? '-'),
            'kapasitas_btu'  => $btu !== '' ? $btu : ($row['kapasitas_btu'] ?? null),
            'bmn_no_display' => $bmn !== '' ? $bmn : ($row['bmn_no_display'] ?? null),
            'lokasi'         => $lokasi ?: ($row['lokasi'] ?? '-'),
            'status_ac'      => $statusFinal,
            'catatan'        => $this->upsertSn($row['catatan'] ?? null, $sn),
        ];

        try {
            if (!$AC->save($data)) {
                return redirect()->back()->withInput()->with('err','Validasi gagal: '.json_encode($AC->errors()));
            }
        } catch (DatabaseException $e) {
            return redirect()->back()->withInput()->with('err','DB error: '.$e->getMessage());
        }

        /* ====== FOTO AC (opsional) ====== */
        $dir = FCPATH.'uploads/ac_units/'.$id;
        $remove = (int)$this->request->getPost('remove_photo') === 1;

        if ($remove) {
            $this->deletePhotoFiles($dir);
        }

        $file = $this->request->getFile('foto');
        if ($file && $file->isValid()) {
            $ext = strtolower($file->getClientExtension() ?: $file->getExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $this->deletePhotoFiles($dir);
            $file->move($dir, 'main.'.$ext, true);
        }

        return redirect()->route('admin.ac.show',[$id])->with('ok','Data berhasil diperbarui');
    }

    /* DELETE (hapus anak + folder foto + file QR) */
    public function delete(int $id)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return redirect()->back()->with('err','Method Not Allowed');
        }

        $AC  = new AcUnitModel();
        $row = $AC->find($id);
        if (!$row) return redirect()->route('admin.ac.index')->with('err','Data tidak ditemukan');

        $db = \Config\Database::connect();
        $db->transBegin();

        try {
            (new AcRepairModel())->where('ac_id',$id)->delete();
            (new AcTicketModel())->where('ac_id',$id)->delete();
            $db->table('ac_units')->where('id',$id)->delete();
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->route('admin.ac.show',[$id])->with('err','Gagal hapus: '.$e->getMessage());
        }

        // bersihkan file
        $this->rrmdir(FCPATH.'uploads/ac_units/'.$id);
        if (!empty($row['kode_qr'])) {
            @unlink(FCPATH.'uploads/qrcodes/'.($row['kode_qr']).'.png');
        }

        return redirect()->route('admin.ac.index')->with('ok','Data berhasil dihapus');
    }

    /* DOWNLOAD QR (generate kalau belum ada) */
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
            if (class_exists('\\chillerlan\\QRCode\\QRCode') && class_exists('\\chillerlan\\QRCode\\QROptions')) {
                $opts = new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel'   => \chillerlan\QRCode\QRCode::ECC_L,
                    'scale'      => 8,
                    'imageBase64'=> false,
                    'margin'     => 2,
                ]);
                $png = (new \chillerlan\QRCode\QRCode($opts))->render($dataUrl);
            } else {
                $api = 'https://api.qrserver.com/v1/create-qr-code/?size=512x512&qzone=2&data='.rawurlencode($dataUrl);
                $png = @file_get_contents($api);
                if ($png === false) {
                    return redirect()->route('admin.ac.show', [$id])->with('err','Gagal generate QR.');
                }
            }
            @file_put_contents($diskFile, $png);
        }

        $safeName = $this->slugify(($row['nomor_unik'] ?? 'ac').'-qr').'.png';
        return $this->response->download($diskFile, null)->setFileName($safeName);
    }

    public function bulkDelete()
    {
    if ($this->request->getMethod(true) !== 'POST') {
        return redirect()->back()->with('err','Method Not Allowed');
    }

    $ids = $this->request->getPost('ids');
    if (!$ids || !is_array($ids)) {
        return redirect()->route('admin.ac.index')->with('err','Tidak ada data yang dipilih.');
    }

    // Normalisasi: int unik & > 0
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids, static fn($v) => $v > 0);
    if (empty($ids)) {
        return redirect()->route('admin.ac.index')->with('err','Tidak ada data yang dipilih.');
    }

    $db = \Config\Database::connect();
    $AC = new AcUnitModel();

    $db->transBegin();
    try {
        foreach ($ids as $id) {
            $row = $AC->find($id);
            if (!$row) continue;

            // hapus anak
            (new AcRepairModel())->where('ac_id', $id)->delete();
            (new AcTicketModel())->where('ac_id', $id)->delete();

            // hapus induk
            $db->table('ac_units')->where('id', $id)->delete();

            // hapus file
            $this->rrmdir(FCPATH.'uploads/ac_units/'.$id);
            if (!empty($row['kode_qr'])) {
                @unlink(FCPATH.'uploads/qrcodes/'.($row['kode_qr']).'.png');
            }
        }
        $db->transCommit();
    } catch (\Throwable $e) {
        $db->transRollback();
        return redirect()->route('admin.ac.index')->with('err','Gagal hapus: '.$e->getMessage());
    }

    return redirect()->route('admin.ac.index')->with('ok','Berhasil hapus '.count($ids).' perangkat.');
    }


    /* ====== EXPORT EXCEL / CSV (ikut filter) ====== */
    public function export()
    {
        $q      = trim($this->request->getGet('q') ?? '');
        $status = trim($this->request->getGet('status') ?? '');

        $m = new AcUnitModel();
        $b = $m->select('id,nomor_unik,tipe_model,kapasitas_btu,bmn_no_display,lokasi,status_ac')
               ->orderBy('id','DESC');

        if ($q !== '') {
            $b = $b->groupStart()
                   ->like('nomor_unik', $q)
                   ->orLike('tipe_model', $q)
                   ->orLike('kapasitas_btu', $q)
                   ->orLike('bmn_no_display', $q)
                   ->orLike('lokasi', $q)
                 ->groupEnd();
        }
        if ($status !== '' && in_array($status, self::ALLOWED_STATUS, true)) {
            $b = $b->where('status_ac', $status);
        }

        $rows = $b->get()->getResultArray() ?? [];

        $headers = ['ID','Nama','Tipe/Model','Kapasitas (BTU)','No. BMN','Lokasi','Status'];
        $data = array_map(static function(array $r){
            return [
                'ID'              => (int)($r['id'] ?? 0),
                'Nama'            => (string)($r['nomor_unik'] ?? ''),
                'Tipe/Model'      => (string)($r['tipe_model'] ?? ''),
                'Kapasitas (BTU)' => (string)($r['kapasitas_btu'] ?? ''),
                'No. BMN'         => (string)($r['bmn_no_display'] ?? ''),
                'Lokasi'          => (string)($r['lokasi'] ?? ''),
                'Status'          => (string)($r['status_ac'] ?? ''),
            ];
        }, $rows);

        $filenameBase = 'data-ac-'.date('Ymd-His');

        // XLSX jika PhpSpreadsheet tersedia
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('AC Units');

            // Header
            $col=1;
            foreach ($headers as $h) { $sheet->setCellValueByColumnAndRow($col++, 1, $h); }
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);

            // Rows
            $rnum = 2;
            foreach ($data as $row) {
                $col = 1;
                foreach ($headers as $h) {
                    $sheet->setCellValueByColumnAndRow($col++, $rnum, $row[$h] ?? '');
                }
                $rnum++;
            }

            // Wrap & autosize
            $last = max(1, $rnum-1);
            $sheet->getStyle("A1:G{$last}")->getAlignment()->setWrapText(true);
            foreach (range('A','G') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }
            $sheet->freezePane('A2');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            ob_start(); $writer->save('php://output'); $bin = ob_get_clean();

            return $this->response
                ->setHeader('Content-Type','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition','attachment; filename="'.$filenameBase.'.xlsx"')
                ->setBody($bin);
        }

        // Fallback CSV (pakai BOM biar Excel Windows aman UTF-8)
        $fh = fopen('php://temp', 'r+');
        // BOM
        fwrite($fh, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($fh, $headers);
        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $h) { $line[] = $row[$h] ?? ''; }
            fputcsv($fh, $line);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $this->response
            ->setHeader('Content-Type','text/csv; charset=utf-8')
            ->setHeader('Content-Disposition','attachment; filename="'.$filenameBase.'.csv"')
            ->setBody($csv);
    }

    /* ================== Helpers ================== */

    /** angka kartu */
    private function calcStats(): array
    {
        $db  = \Config\Database::connect();
        $row = $db->query("
            SELECT
              COUNT(*)                                  AS total,
              SUM(status_ac = 'RUSAK_RINGAN')           AS ringan,
              SUM(status_ac = 'RUSAK_BERAT')            AS berat,
              SUM(status_ac = 'NORMAL')                 AS normal
            FROM ac_units
        ")->getRowArray() ?: [];

        return [
            'total'   => (int)($row['total']   ?? 0),
            'ringan'  => (int)($row['ringan']  ?? 0),
            'berat'   => (int)($row['berat']   ?? 0),
            'normal'  => (int)($row['normal']  ?? 0),
        ];
    }

    private function slugify(string $text): string
    {
        $text = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT', $text) : $text;
        $text = preg_replace('~[^\\pL\\d]+~u', '-', $text);
        $text = trim($text, '-');
        $text = preg_replace('~[^-\\w]+~', '', $text);
        return strtolower($text ?: 'qr');
    }

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
        $sn  = trim($sn);
        $cat = $catatan ? trim($catatan) : '';
        if ($sn==='') return $cat ?: null;
        if ($cat==='') return "SN=".$sn;
        if (preg_match('/\bSN\s*=\s*[^\r\n]+/i',$cat)) {
            return preg_replace('/\bSN\s*=\s*[^\r\n]+/i','SN='.$sn,$cat);
        }
        return rtrim($cat)."\nSN=".$sn;
    }

    private function findPhotoUrl(int $id): ?string
    {
        $dir = FCPATH.'uploads/ac_units/'.$id;
        foreach (['jpg','jpeg','png','webp'] as $x) {
            $p = $dir.'/main.'.$x;
            if (is_file($p)) return base_url('uploads/ac_units/'.$id.'/main.'.$x).'?v='.filemtime($p);
        }
        return null;
    }

    private function deletePhotoFiles(string $dir): void
    {
        foreach (['jpg','jpeg','png','webp'] as $x) { @unlink($dir.'/main.'.$x); }
    }

    private function rrmdir(string $path): void
    {
        if (!is_dir($path)) return;
        $it    = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($path);
    }
}
