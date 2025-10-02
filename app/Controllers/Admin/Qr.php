<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AcUnitModel;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Qr extends BaseController
{
    public function index()
    {
        return view('Admin/qr/index', [
            'title'      => 'Generate QR',
            'activeMenu' => 'qr',
        ]);
    }

    // POST /admin/qr/save  — insert adaptif sesuai skema tabel
    public function save()
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setJSON(['error' => 'Method Not Allowed'])
                                  ->setStatusCode(ResponseInterface::HTTP_METHOD_NOT_ALLOWED);
        }

        $r = $this->request;

        // Ambil input
        $token   = trim((string)$r->getPost('token'));      // boleh kosong → auto-generate
        $nama    = trim((string)$r->getPost('nama'));
        $merek   = trim((string)$r->getPost('merek'));
        $model   = trim((string)$r->getPost('model'));
        $serial  = trim((string)$r->getPost('serial_no'));
        $lokasi  = trim((string)$r->getPost('lokasi'));
        $kodeOp  = trim((string)$r->getPost('kode_qr'));    // catatan internal (opsional)
        $statusI = strtoupper(trim((string)$r->getPost('status') ?: 'NORMAL'));

        if ($nama === '') {
            return $this->response->setJSON(['error' => 'Nama wajib diisi'])
                                  ->setStatusCode(ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        $AC = new AcUnitModel();

        // === 1) Cek skema minimum & ambil daftar kolom tabel
        $table = $AC->table;
        $cols  = $this->listColumns($table);        // ['Field' => meta..]
        if (!$cols) {
            return $this->response->setJSON(['error' => "Tabel `$table` tidak ditemukan"])
                                  ->setStatusCode(500);
        }

        // === 2) Pastikan token (kode_qr) ada & unik — jika kolomnya ada
        $kodeQrCol = 'kode_qr';
        if (isset($cols[$kodeQrCol])) {
            if ($token === '') {
                $token = $this->makeKodeQrUnique($AC);
            } else {
                if ($AC->where($kodeQrCol, $token)->first()) {
                    return $this->response->setJSON(['error' => "$kodeQrCol sudah dipakai"])
                                          ->setStatusCode(ResponseInterface::HTTP_CONFLICT);
                }
            }
        }

        // === 3) Bentuk data calon insert
        // Rapikan nama (tanpa suffix, uppercase)
        $nomorUnik = $this->normalizeName($nama);

        $tipeModel = trim(($merek ? $merek.' ' : '').$model);
        $catatan   = [];
        if ($kodeOp !== '') $catatan[] = 'KODE='.$kodeOp;
        if ($serial  !== '') $catatan[] = 'SN='.$serial;

        $candidate = [
            'kode_qr'       => $token ?: null,
            'nomor_unik'    => $nomorUnik ?: null,
            'tipe_model'    => ($tipeModel !== '' ? $tipeModel : '-'),
            'kapasitas_btu' => 12000,
            'lokasi'        => ($lokasi !== '' ? $lokasi : '-'),
            'status_ac'     => $statusI,
            'catatan'       => ($catatan ? implode("\n", $catatan) : null),
        ];

        // === 4) TRIM KE KOLOM YANG ADA SAJA (hindari "Unknown column")
        $data = [];
        foreach ($candidate as $k => $v) {
            if (isset($cols[$k])) $data[$k] = $v;
        }

        // === 5) ENUM guard utk status_ac (kalau kolomnya ENUM)
        if (isset($data['status_ac']) && isset($cols['status_ac'])) {
            $allowed = $this->parseEnumAllowed($cols['status_ac']['Type']);
            if ($allowed && !in_array($data['status_ac'], $allowed, true)) {
                // fallback ke nilai pertama ENUM
                $data['status_ac'] = $allowed[0];
            }
        }

        // === 6) Isi default utk kolom NOT NULL tanpa default
        foreach ($cols as $name => $meta) {
            if (!array_key_exists($name, $data)) {
                // NOT NULL? kasih nilai aman default
                $isNotNull = (strpos(strtoupper($meta['Null'] ?? ''), 'NO') !== false);
                $hasDefault= array_key_exists('Default', $meta) && $meta['Default'] !== null;
                if ($isNotNull && !$hasDefault) {
                    $type = strtolower($meta['Type'] ?? 'varchar(191)');
                    if (strpos($type, 'int') !== false)       $data[$name] = 0;
                    elseif (strpos($type, 'enum(') !== false) $data[$name] = $this->parseEnumAllowed($type)[0] ?? '';
                    elseif (strpos($type, 'varchar') !== false) $data[$name] = '';
                    elseif ($type === 'text')                 $data[$name] = '';
                    else                                      $data[$name] = '';
                }
            }
        }

        // === 7) Insert dgn detail error bila gagal
        try {
            $id = $AC->insert($data, true);
            if ($id === false) {
                return $this->response->setJSON([
                    'error'      => 'Gagal simpan (validasi)',
                    'validation' => $AC->errors(),
                    'db_error'   => $AC->db->error(),
                    'last_query' => (string)$AC->db->getLastQuery(),
                    'data'       => $data,
                    'columns'    => array_keys($cols),
                ])->setStatusCode(ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
            }
        } catch (DatabaseException $e) {
            log_message('error', 'DB exception /admin/qr/save: {msg} | SQL: {sql}', [
                'msg' => $e->getMessage(),
                'sql' => (string)$AC->db->getLastQuery(),
            ]);
            return $this->response->setJSON([
                'error'      => 'DB exception',
                'message'    => $e->getMessage(),
                'code'       => $e->getCode(),
                'db_error'   => $AC->db->error(),
                'last_query' => (string)$AC->db->getLastQuery(),
                'data'       => $data,
                'columns'    => array_keys($cols),
            ])->setStatusCode(500);
        } catch (\Throwable $e) {
            return $this->response->setJSON([
                'error'   => 'Server exception',
                'message' => $e->getMessage(),
            ])->setStatusCode(500);
        }

        // === 8) Upload foto opsional
        $foto = $r->getFile('foto');
        if ($foto && $foto->isValid()) {
            $dir = FCPATH.'uploads/ac_units/'.$id;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return $this->response->setJSON(['error' => 'Gagal membuat folder upload'])->setStatusCode(500);
            }
            $ext = strtolower($foto->getClientExtension() ?: $foto->getExtension() ?: $foto->guessExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            foreach (['jpg','jpeg','png','webp'] as $x) { @unlink($dir.'/main.'.$x); }
            $foto->move($dir, 'main.'.$ext, true);
        }

        return $this->response->setJSON([
            'ok'  => true,
            'id'  => (int)$id,
            'url' => site_url('ac/'.($data[$kodeQrCol] ?? $token ?? '')),
        ]);
    }

    /** DIAG: cek skema & index unik */
    public function diag()
    {
        $AC   = new AcUnitModel();
        $cols = $this->listColumns($AC->table);
        $uni  = $this->listUniqueColumns($AC->table);

        return $this->response->setJSON([
            'table'   => $AC->table,
            'columns' => $cols,
            'uniques' => $uni,
        ]);
    }

    /** TEST: lakukan insert dummy dalam transaksi lalu rollback (untuk uji cepat) */
    public function testInsert()
    {
        $AC = new AcUnitModel();
        $db = \Config\Database::connect();
        $db->transBegin();

        $cols = $this->listColumns($AC->table);
        $data = [];
        if (isset($cols['kode_qr']))    $data['kode_qr']    = bin2hex(random_bytes(8));
        if (isset($cols['nomor_unik'])) $data['nomor_unik'] = 'TEST UNIT';
        if (isset($cols['tipe_model'])) $data['tipe_model'] = 'TEST';
        if (isset($cols['kapasitas_btu'])) $data['kapasitas_btu'] = 9000;
        if (isset($cols['lokasi']))     $data['lokasi']     = '-';
        if (isset($cols['status_ac']))  $data['status_ac']  = ($this->parseEnumAllowed($cols['status_ac']['Type'])[0] ?? 'NORMAL');

        try {
            $ok = $db->table($AC->table)->insert($data);
            $last = (string)$db->getLastQuery();
            $db->transRollback(); // rollback supaya tidak tersimpan
            return $this->response->setJSON([
                'ok'         => (bool)$ok,
                'test_data'  => $data,
                'last_query' => $last,
                'db_error'   => $db->error(),
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'error'      => 'DB exception during testInsert',
                'message'    => $e->getMessage(),
                'last_query' => (string)$db->getLastQuery(),
                'db_error'   => $db->error(),
            ])->setStatusCode(500);
        }
    }

    /* ============== Helpers ============== */

    /** Buat kode_qr unik */
    private function makeKodeQrUnique(AcUnitModel $AC, int $bytes = 16): string
    {
        for ($i = 0; $i < 5; $i++) {
            $t = bin2hex(random_bytes($bytes));
            if (!$AC->where('kode_qr', $t)->first()) return $t;
        }
        return bin2hex(random_bytes($bytes * 2));
    }

    /** Rapikan nama (uppercase, trim) */
    private function normalizeName(string $text): string
    {
        $t = preg_replace('/\s+/', ' ', trim($text));
        if ($t === '') $t = 'AC';
        return mb_strtoupper($t, 'UTF-8');
    }

    /** Ambil daftar kolom: map[name] = meta (Type, Null, Key, Default, Extra) */
    private function listColumns(string $table): array
    {
        $db  = \Config\Database::connect();
        $res = $db->query("SHOW COLUMNS FROM `{$table}`")->getResultArray();
        $map = [];
        foreach ($res as $r) { $map[$r['Field']] = $r; }
        return $map;
    }

    /** Ambil daftar kolom yang punya unique index */
    private function listUniqueColumns(string $table): array
    {
        $db  = \Config\Database::connect();
        $res = $db->query("
            SELECT DISTINCT COLUMN_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND NON_UNIQUE   = 0
        ", [$table])->getResultArray();
        return array_map(fn($r) => $r['COLUMN_NAME'], $res);
    }

    /** Parse daftar nilai ENUM dari definisi kolom */
    private function parseEnumAllowed(?string $type): array
    {
        if (!$type) return [];
        if (stripos($type, 'enum(') === false) return [];
        if (!preg_match('/enum\((.*)\)/i', $type, $m)) return [];
        $inside = $m[1];
        // pecah 'A','B','C'
        $vals = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $inside); // split di koma di luar quote
        $out  = [];
        foreach ($vals as $v) { $out[] = trim($v, " '"); }
        return $out;
    }
}
