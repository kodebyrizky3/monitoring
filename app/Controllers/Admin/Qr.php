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

    private function withCsrf(array $payload): array
    {
        if (function_exists('csrf_hash')) {
            $payload['csrf']       = csrf_hash();
            $payload['csrf_token'] = csrf_token();
        }
        return $payload;
    }

    public function save()
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setJSON($this->withCsrf(['error' => 'Method Not Allowed']))
                                  ->setStatusCode(ResponseInterface::HTTP_METHOD_NOT_ALLOWED);
        }

        // === VALIDASI INPUT
        $rules = [
            'nama'            => 'required|max_length[100]',   // dipotong lagi ke 64 utk DB
            'merek'           => 'permit_empty|max_length[50]',
            'model'           => 'permit_empty|max_length[50]',
            'serial_no'       => 'permit_empty|max_length[100]',
            'lokasi'          => 'permit_empty|max_length[120]',
            'base'            => 'permit_empty|valid_url_strict',
            'kapasitas_btu'   => 'permit_empty|regex_match[/^\d{1,7}$/]',
            'bmn_no_display'  => 'permit_empty|regex_match[/^\d{1,30}$/]',
            // status di DB: NORMAL / RUSAK_RINGAN / RUSAK_BERAT
            'status'          => 'permit_empty|in_list[NORMAL,RUSAK_RINGAN,RUSAK_BERAT]',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setJSON($this->withCsrf([
                'error'      => 'Validasi gagal',
                'validation' => $this->validator->getErrors(),
            ]))->setStatusCode(422);
        }

        $r = $this->request;

        // === Ambil input
        $token   = trim((string)$r->getPost('token')); // dibuat di JS
        $nama    = trim((string)$r->getPost('nama'));
        $merek   = trim((string)$r->getPost('merek'));
        $model   = trim((string)$r->getPost('model'));
        $serial  = trim((string)$r->getPost('serial_no'));
        $lokasi  = trim((string)$r->getPost('lokasi'));
        $statusI = strtoupper(trim((string)($r->getPost('status') ?: 'NORMAL'))); // default NORMAL

        // angka-only
        $kapBTU = (int)preg_replace('/\D+/', '', (string)($r->getPost('kapasitas_btu') ?? '12000'));
        if ($kapBTU <= 0) $kapBTU = 12000;
        $bmn    = preg_replace('/\D+/', '', (string)($r->getPost('bmn_no_display') ?? ''));

        $AC    = new AcUnitModel();
        $table = $AC->table;
        $cols  = $this->listColumns($table);
        if (!$cols) {
            return $this->response->setJSON($this->withCsrf(['error' => "Tabel `$table` tidak ditemukan"]))
                                  ->setStatusCode(500);
        }
        $uniques   = $this->listUniqueColumns($table);
        $hasSerial = isset($cols['serial_no']);

        // === Pastikan token (kode_qr) unik
        if (isset($cols['kode_qr'])) {
            if ($token === '') {
                $token = $this->makeKodeQrUnique($AC);
            } else {
                if ($AC->where('kode_qr', $token)->first()) {
                    return $this->response->setJSON($this->withCsrf(['error' => 'kode_qr sudah dipakai']))
                                          ->setStatusCode(ResponseInterface::HTTP_CONFLICT);
                }
            }
        }

        // === Bentuk nilai
        $nomorUnik = $this->normalizeName($nama);
        // jaga batas DB: varchar(64)
        $nomorUnik = mb_substr($nomorUnik, 0, 64, 'UTF-8');

        // Jika kolom nomor_unik unique → buat unik dengan suffix (#2, #3, ...)
        if (in_array('nomor_unik', $uniques, true)) {
            $nomorUnik = $this->makeUniqueValue($AC, 'nomor_unik', $nomorUnik, 64);
        }

        $tipeModel = trim(($merek ? $merek.' ' : '').$model);
        if (mb_strlen($tipeModel, 'UTF-8') > 120) {
            $tipeModel = mb_substr($tipeModel, 0, 120, 'UTF-8');
        }

        $candidate = [
            'kode_qr'        => $token ?: null,
            'nomor_unik'     => $nomorUnik ?: null,
            'tipe_model'     => ($tipeModel !== '' ? $tipeModel : '-'),
            'kapasitas_btu'  => $kapBTU,
            'lokasi'         => ($lokasi !== '' ? $lokasi : '-'),
            'bmn_no_display' => ($bmn !== '' ? $bmn : null),
            'status_ac'      => $statusI,
        ];
        if ($hasSerial) {
            $candidate['serial_no'] = ($serial !== '') ? $serial : null;
        }

        // pilih hanya kolom yg ada
        $data = [];
        foreach ($candidate as $k => $v) {
            if (isset($cols[$k])) $data[$k] = $v;
        }

        // enum guard (pakai nilai pertama kalau tidak valid)
        if (isset($data['status_ac'], $cols['status_ac'])) {
            $allowed = $this->parseEnumAllowed($cols['status_ac']['Type'] ?? null);
            if ($allowed && !in_array($data['status_ac'], $allowed, true)) {
                $data['status_ac'] = $allowed[0];
            }
        }

        // default utk NOT NULL tanpa default
        foreach ($cols as $name => $meta) {
            if (!array_key_exists($name, $data)) {
                $isNotNull = (strpos(strtoupper($meta['Null'] ?? ''), 'NO') !== false);
                $hasDefault= array_key_exists('Default', $meta) && $meta['Default'] !== null;
                if ($isNotNull && !$hasDefault) {
                    $type = strtolower($meta['Type'] ?? 'varchar(191)');
                    if (strpos($type, 'int') !== false)          $data[$name] = 0;
                    elseif (strpos($type, 'enum(') !== false)    $data[$name] = $this->parseEnumAllowed($type)[0] ?? '';
                    else                                         $data[$name] = '';
                }
            }
        }

        // === Insert
        try {
            $id = $AC->protect(false)->insert($data, true);
            if ($id === false) {
                return $this->response->setJSON($this->withCsrf([
                    'error'      => 'Gagal simpan (validasi model)',
                    'validation' => $AC->errors(),
                    'db_error'   => $AC->db->error(),
                    'last_query' => (string)$AC->db->getLastQuery(),
                ]))->setStatusCode(422);
            }
        } catch (DatabaseException $e) {
            return $this->response->setJSON($this->withCsrf([
                'error'      => 'DB exception',
                'message'    => $e->getMessage(),
                'db_error'   => $AC->db->error(),
                'last_query' => (string)$AC->db->getLastQuery(),
                'data'       => $data,
            ]))->setStatusCode(500);
        } catch (\Throwable $e) {
            return $this->response->setJSON($this->withCsrf([
                'error'   => 'Server exception',
                'message' => $e->getMessage(),
            ]))->setStatusCode(500);
        }

        // Upload foto opsional
        $foto = $r->getFile('foto');
        if ($foto && $foto->isValid()) {
            $dir = FCPATH.'uploads/ac_units/'.$id;
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return $this->response->setJSON($this->withCsrf(['error' => 'Gagal membuat folder upload']))->setStatusCode(500);
            }
            $ext = strtolower($foto->getClientExtension() ?: $foto->getExtension() ?: $foto->guessExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            foreach (['jpg','jpeg','png','webp'] as $x) @unlink($dir.'/main.'.$x);
            $foto->move($dir, 'main.'.$ext, true);
        }

        return $this->response->setJSON($this->withCsrf([
            'ok'  => true,
            'id'  => (int)$id,
            'url' => site_url('ac/'.($data['kode_qr'] ?? $token)),
        ]));
    }

    /**
     * ====== BULK SAVE: simpan banyak AC sekaligus ======
     * Input: POST form-data dengan field "rows" berisi JSON array:
     * [
     *   ["Nama","Merek","Model","Serial No","Lokasi","BTU","BMN","Status"],
     *   ...
     * ]
     * Atau objek:
     * [
     *   {"nama":"...","merek":"...","model":"...","serial_no":"...","lokasi":"...","kapasitas_btu":"12000","bmn_no_display":"...","status":"NORMAL"},
     *   ...
     * ]
     */
    public function bulkSave()
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setJSON($this->withCsrf(['error' => 'Method Not Allowed']))
                                  ->setStatusCode(ResponseInterface::HTTP_METHOD_NOT_ALLOWED);
        }

        // Ambil payload "rows" dari form-data
        $rowsRaw = $this->request->getPost('rows');
        if (!$rowsRaw) {
            // fallback: JSON body
            $json = $this->request->getJSON(true);
            $rows = $json['rows'] ?? $json ?? null;
        } else {
            $rows = json_decode($rowsRaw, true);
        }

        if (!is_array($rows)) {
            return $this->response->setJSON($this->withCsrf(['error' => 'Payload tidak valid (rows)']))
                                  ->setStatusCode(422);
        }

        // Normalisasi: boleh berupa array of arrays (CSV) atau array of objects
        $norm = [];
        foreach ($rows as $r) {
            if (is_array($r) && array_values($r) === $r) {
                // array numerik: pakai urutan kolom tetap
                $norm[] = [
                    'nama'           => trim((string)($r[0] ?? '')),
                    'merek'          => trim((string)($r[1] ?? '')),
                    'model'          => trim((string)($r[2] ?? '')),
                    'serial_no'      => trim((string)($r[3] ?? '')),
                    'lokasi'         => trim((string)($r[4] ?? '')),
                    'kapasitas_btu'  => (string)($r[5] ?? ''),
                    'bmn_no_display' => (string)($r[6] ?? ''),
                    'status'         => (string)($r[7] ?? 'NORMAL'),
                ];
            } else {
                // object/assoc
                $norm[] = [
                    'nama'           => trim((string)($r['nama'] ?? '')),
                    'merek'          => trim((string)($r['merek'] ?? '')),
                    'model'          => trim((string)($r['model'] ?? '')),
                    'serial_no'      => trim((string)($r['serial_no'] ?? '')),
                    'lokasi'         => trim((string)($r['lokasi'] ?? '')),
                    'kapasitas_btu'  => (string)($r['kapasitas_btu'] ?? ''),
                    'bmn_no_display' => (string)($r['bmn_no_display'] ?? ''),
                    'status'         => (string)($r['status'] ?? 'NORMAL'),
                    'token'          => (string)($r['token'] ?? $r['kode_qr'] ?? ''), // opsional
                ];
            }
        }

        $maxRows = 1000;
        if (count($norm) === 0) {
            return $this->response->setJSON($this->withCsrf(['error' => 'Tidak ada baris data']))
                                  ->setStatusCode(422);
        }
        if (count($norm) > $maxRows) {
            return $this->response->setJSON($this->withCsrf(['error' => "Maksimal {$maxRows} baris per unggahan"]))
                                  ->setStatusCode(413);
        }

        $AC    = new AcUnitModel();
        $table = $AC->table;
        $cols  = $this->listColumns($table);
        if (!$cols) {
            return $this->response->setJSON($this->withCsrf(['error' => "Tabel `$table` tidak ditemukan"]))
                                  ->setStatusCode(500);
        }
        $uniques   = $this->listUniqueColumns($table);
        $hasSerial = isset($cols['serial_no']);
        $hasKode   = isset($cols['kode_qr']);

        $allowedStatus = isset($cols['status_ac'])
            ? $this->parseEnumAllowed($cols['status_ac']['Type'] ?? null)
            : ['NORMAL','RUSAK_RINGAN','RUSAK_BERAT'];

        $results = [];
        $okCount = 0;

        foreach ($norm as $i => $row) {
            $rowNum = $i + 1; // human-friendly
            // Validasi minimal
            if ($row['nama'] === '') {
                $results[] = [
                    'row'   => $rowNum,
                    'ok'    => false,
                    'error' => 'Nama wajib diisi',
                ];
                continue;
            }

            // angka-only
            $kapBTU = (int)preg_replace('/\D+/', '', (string)($row['kapasitas_btu'] ?? '12000'));
            if ($kapBTU <= 0) $kapBTU = 12000;
            $bmn = preg_replace('/\D+/', '', (string)($row['bmn_no_display'] ?? ''));

            // status
            $statusI = strtoupper(trim((string)($row['status'] ?: 'NORMAL')));
            $statusI = str_replace(' ', '_', $statusI);
            if (!in_array($statusI, $allowedStatus, true)) {
                $statusI = $allowedStatus[0] ?? 'NORMAL';
            }

            // token (kode_qr)
            $token = trim((string)($row['token'] ?? ''));
            if ($hasKode) {
                if ($token === '' || $AC->where('kode_qr', $token)->first()) {
                    $token = $this->makeKodeQrUnique($AC);
                }
            } else {
                $token = null;
            }

            // nomor_unik (maks 64) + unik jika perlu
            $nomorUnik = $this->normalizeName((string)$row['nama']);
            $nomorUnik = mb_substr($nomorUnik, 0, 64, 'UTF-8');
            if (in_array('nomor_unik', $uniques, true)) {
                $nomorUnik = $this->makeUniqueValue($AC, 'nomor_unik', $nomorUnik, 64);
            }

            // tipe_model
            $merek = (string)$row['merek'];
            $model = (string)$row['model'];
            $tipeModel = trim(($merek ? $merek.' ' : '').$model);
            if (mb_strlen($tipeModel, 'UTF-8') > 120) {
                $tipeModel = mb_substr($tipeModel, 0, 120, 'UTF-8');
            }

            $lokasi = trim((string)$row['lokasi']);
            $serial = trim((string)$row['serial_no']);

            $candidate = [
                'kode_qr'        => $token ?: null,
                'nomor_unik'     => $nomorUnik ?: null,
                'tipe_model'     => ($tipeModel !== '' ? $tipeModel : '-'),
                'kapasitas_btu'  => $kapBTU,
                'lokasi'         => ($lokasi !== '' ? $lokasi : '-'),
                'bmn_no_display' => ($bmn !== '' ? $bmn : null),
                'status_ac'      => $statusI,
            ];
            if ($hasSerial) {
                $candidate['serial_no'] = ($serial !== '') ? $serial : null;
            }

            // pilih hanya kolom yg ada
            $data = [];
            foreach ($candidate as $k => $v) {
                if (isset($cols[$k])) $data[$k] = $v;
            }

            // enum guard
            if (isset($data['status_ac'], $cols['status_ac'])) {
                $allowed = $this->parseEnumAllowed($cols['status_ac']['Type'] ?? null);
                if ($allowed && !in_array($data['status_ac'], $allowed, true)) {
                    $data['status_ac'] = $allowed[0];
                }
            }

            // default utk NOT NULL tanpa default
            foreach ($cols as $name => $meta) {
                if (!array_key_exists($name, $data)) {
                    $isNotNull = (strpos(strtoupper($meta['Null'] ?? ''), 'NO') !== false);
                    $hasDefault= array_key_exists('Default', $meta) && $meta['Default'] !== null;
                    if ($isNotNull && !$hasDefault) {
                        $type = strtolower($meta['Type'] ?? 'varchar(191)');
                        if (strpos($type, 'int') !== false)          $data[$name] = 0;
                        elseif (strpos($type, 'enum(') !== false)    $data[$name] = $this->parseEnumAllowed($type)[0] ?? '';
                        else                                         $data[$name] = '';
                    }
                }
            }

            try {
                $id = $AC->protect(false)->insert($data, true);
                if ($id === false) {
                    $results[] = [
                        'row'   => $rowNum,
                        'ok'    => false,
                        'error' => 'Gagal simpan (validasi model)',
                        'validation' => $AC->errors(),
                        'db_error'   => $AC->db->error(),
                    ];
                    continue;
                }
                $okCount++;
                $results[] = [
                    'row'   => $rowNum,
                    'ok'    => true,
                    'id'    => (int)$id,
                    'token' => $data['kode_qr'] ?? null,
                    'url'   => site_url('ac/'.($data['kode_qr'] ?? '')),
                ];
            } catch (DatabaseException $e) {
                $results[] = [
                    'row'   => $rowNum,
                    'ok'    => false,
                    'error' => 'DB exception: '.$e->getMessage(),
                    'db_error' => $AC->db->error(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'row'   => $rowNum,
                    'ok'    => false,
                    'error' => 'Server exception: '.$e->getMessage(),
                ];
            }
        }

        return $this->response->setJSON($this->withCsrf([
            'ok'       => true,
            'total'    => count($norm),
            'success'  => $okCount,
            'failed'   => count($norm) - $okCount,
            'results'  => $results,
        ]));
    }

    /* ============== Helpers ============== */

    private function makeKodeQrUnique(AcUnitModel $AC, int $bytes = 16): string
    {
        for ($i = 0; $i < 5; $i++) {
            $t = bin2hex(random_bytes($bytes));
            if (!$AC->where('kode_qr', $t)->first()) return $t;
        }
        return bin2hex(random_bytes($bytes * 2));
    }

    /** Pastikan nilai unik pada kolom UNIQUE (dengan suffix #2, #3, ...) */
    private function makeUniqueValue(AcUnitModel $AC, string $column, string $base, int $maxLen = 64): string
    {
        $val = $base;
        $i = 2;
        while ($AC->where($column, $val)->first()) {
            $suffix = ' #'.$i;
            $val = mb_substr($base, 0, $maxLen - mb_strlen($suffix, 'UTF-8'), 'UTF-8') . $suffix;
            $i++;
            if ($i > 9999) break;
        }
        return $val;
    }

    private function normalizeName(string $text): string
    {
        $t = preg_replace('/\s+/', ' ', trim($text));
        if ($t === '') $t = 'AC';
        return mb_strtoupper($t, 'UTF-8');
    }

    private function listColumns(string $table): array
    {
        $db  = \Config\Database::connect();
        $res = $db->query("SHOW COLUMNS FROM `{$table}`")->getResultArray();
        $map = [];
        foreach ($res as $r) $map[$r['Field']] = $r;
        return $map;
    }

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

    private function parseEnumAllowed(?string $type): array
    {
        if (!$type || stripos($type, 'enum(') === false) return [];
        if (!preg_match('/enum\((.*)\)/i', $type, $m)) return [];
        $inside = $m[1];
        $vals = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $inside);
        return array_map(fn($v) => trim($v, " '"), $vals);
    }
}
