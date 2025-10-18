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

    public function diag()
    {
        return $this->response->setJSON(
            $this->withCsrf(['ok' => true, 'time' => date('c')])
        );
    }

    // opsional - hanya untuk dev bila perlu
    public function opcacheReset()
    {
        if (function_exists('opcache_reset')) @opcache_reset();
        return $this->response->setJSON($this->withCsrf(['ok'=>true]));
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

        $rules = [
            'nama'            => 'required|max_length[100]',
            'merek'           => 'permit_empty|max_length[50]',
            'model'           => 'permit_empty|max_length[50]',
            'serial_no'       => 'permit_empty|max_length[100]',
            'lokasi'          => 'permit_empty|max_length[120]',
            'base'            => 'permit_empty|valid_url_strict',
            'kapasitas_btu'   => 'permit_empty|regex_match[/^\d{1,7}$/]',
            // izinkan angka + . - spasi
            'bmn_no_display'  => 'permit_empty|regex_match[/^[0-9.\-\s]{1,30}$/]',
            'status'          => 'permit_empty|in_list[NORMAL,RUSAK_RINGAN,RUSAK_BERAT]',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setJSON($this->withCsrf([
                'error'      => 'Validasi gagal',
                'validation' => $this->validator->getErrors(),
            ]))->setStatusCode(422);
        }

        $r = $this->request;

        $token   = trim((string)$r->getPost('token'));
        $nama    = trim((string)$r->getPost('nama'));
        $merek   = trim((string)$r->getPost('merek'));
        $model   = trim((string)$r->getPost('model'));
        $serial  = trim((string)$r->getPost('serial_no'));
        $lokasi  = trim((string)$r->getPost('lokasi'));
        $statusI = strtoupper(trim((string)($r->getPost('status') ?: 'NORMAL')));

        $kapBTU = (int)preg_replace('/\D+/', '', (string)($r->getPost('kapasitas_btu') ?? '12000'));
        if ($kapBTU <= 0) $kapBTU = 12000;

        // display yang aman
        $bmnDisp   = trim(preg_replace('/[^0-9.\-\s]+/u', '', (string)($r->getPost('bmn_no_display') ?? '')));
        $bmnDigits = preg_replace('/\D+/', '', $bmnDisp);

        $AC    = new AcUnitModel();
        $table = $AC->table;
        $cols  = $this->listColumns($table);
        if (!$cols) {
            return $this->response->setJSON($this->withCsrf(['error' => "Tabel `$table` tidak ditemukan"]))
                                  ->setStatusCode(500);
        }
        $uniques   = $this->listUniqueColumns($table);
        $hasSerial = isset($cols['serial_no']);

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

        $nomorUnik = $this->normalizeName($nama);
        $nomorUnik = mb_substr($nomorUnik, 0, 64, 'UTF-8');
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
            'bmn_no_display' => ($bmnDisp !== '' ? $bmnDisp : null),
            'status_ac'      => $statusI,
        ];
        if ($hasSerial) $candidate['serial_no'] = ($serial !== '') ? $serial : null;

        $data = [];
        foreach ($candidate as $k => $v) if (isset($cols[$k])) $data[$k] = $v;

        if (isset($data['status_ac'], $cols['status_ac'])) {
            $allowed = $this->parseEnumAllowed($cols['status_ac']['Type'] ?? null);
            if ($allowed && !in_array($data['status_ac'], $allowed, true)) {
                $data['status_ac'] = $allowed[0];
            }
        }

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
                return $this->response->setJSON($this->withCsrf([
                    'error'      => 'Gagal simpan (validasi model)',
                    'validation' => $AC->errors(),
                ]))->setStatusCode(422);
            }
        } catch (DatabaseException $e) {
            return $this->response->setJSON($this->withCsrf([
                'error'    => 'DB exception',
                'message'  => $e->getMessage(),
            ]))->setStatusCode(500);
        } catch (\Throwable $e) {
            return $this->response->setJSON($this->withCsrf([
                'error'   => 'Server exception',
                'message' => $e->getMessage(),
            ]))->setStatusCode(500);
        }

        // Upload foto (single)
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
     * BULK: rows(JSON) + images_zip (opsional)
     * CSV 12 kolom urut:
     * Nama, Merek, Model, Serial No, Lokasi, Kapasitas BTU, Nomor BMN, Status,
     * Tekanan Freon Terakhir, Amper Terakhir, Terakhir Service (DD-MM-YYYY), Terakhir Perawatan (DD-MM-YYYY)
     * Foto ZIP: BMN-only (13 digit), separator di nama file bebas.
     */
    public function bulkSave()
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setJSON($this->withCsrf(['error' => 'Method Not Allowed']))
                                  ->setStatusCode(ResponseInterface::HTTP_METHOD_NOT_ALLOWED);
        }

        @set_time_limit(180);

        // Ambil rows
        $rows = null;
        $rowsRaw = $this->request->getPost('rows');
        if ($rowsRaw) {
            $rows = json_decode($rowsRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->response->setJSON($this->withCsrf([
                    'error' => 'JSON rows tidak valid: ' . json_last_error_msg(),
                ]))->setStatusCode(422);
            }
        } else {
            $json = $this->request->getJSON(true);
            $rows = $json['rows'] ?? $json ?? null;
        }
        if (!is_array($rows)) {
            return $this->response->setJSON($this->withCsrf(['error' => 'Payload tidak valid (rows)']))
                                  ->setStatusCode(422);
        }

        // Normalisasi -> array index 0..11
        $norm = [];
        $skippedNonArray = 0;
        foreach ($rows as $r) {
            if (!is_array($r)) { $skippedNonArray++; continue; }

            if (array_values($r) === $r) {
                $norm[] = [
                    'nama'                    => trim((string)($r[0]  ?? '')),
                    'merek'                   => trim((string)($r[1]  ?? '')),
                    'model'                   => trim((string)($r[2]  ?? '')),
                    'serial_no'               => trim((string)($r[3]  ?? '')),
                    'lokasi'                  => trim((string)($r[4]  ?? '')),
                    'kapasitas_btu'           => (string)($r[5] ?? ''),
                    'bmn_no_display'          => (string)($r[6] ?? ''),
                    'status'                  => (string)($r[7] ?? 'NORMAL'),
                    'tekanan_freon_terakhir'  => (string)($r[8]  ?? ''),
                    'amper_terakhir'          => (string)($r[9]  ?? ''),
                    'terakhir_service'        => (string)($r[10] ?? ''),
                    'terakhir_perawatan'      => (string)($r[11] ?? ''),
                ];
                continue;
            }

            // assoc: support alias ringan
            $norm[] = [
                'nama'   => trim((string)($r['nama'] ?? '')),
                'merek'  => trim((string)($r['merek'] ?? '')),
                'model'  => trim((string)($r['model'] ?? '')),
                'serial_no' => trim((string)($this->firstNotEmpty($r, ['serial_no','serial','sn']) ?? '')),
                'lokasi'    => trim((string)($this->firstNotEmpty($r, ['lokasi','location']) ?? '')),
                'kapasitas_btu'  => (string)($this->firstNotEmpty($r, ['kapasitas_btu','btu']) ?? ''),
                'bmn_no_display' => (string)($this->firstNotEmpty($r, ['bmn_no_display','no_bmn','bmn']) ?? ''),
                'status'         => (string)($this->firstNotEmpty($r, ['status','status_ac']) ?? 'NORMAL'),
                'tekanan_freon_terakhir' => (string)($this->firstNotEmpty($r, ['tekanan_freon_terakhir','freon']) ?? ''),
                'amper_terakhir'         => (string)($this->firstNotEmpty($r, ['amper_terakhir','amper']) ?? ''),
                'terakhir_service'       => (string)($this->firstNotEmpty($r, ['terakhir_service','tgl_service']) ?? ''),
                'terakhir_perawatan'     => (string)($this->firstNotEmpty($r, ['terakhir_perawatan','tgl_perawatan']) ?? ''),
            ];
        }

        $maxRows = 1000;
        if (!count($norm)) {
            return $this->response->setJSON($this->withCsrf([
                'error'   => 'Tidak ada baris data valid',
                'skipped' => $skippedNonArray,
            ]))->setStatusCode(422);
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

        // ZIP foto → BMN-only index
        $zipFile = $this->request->getFile('images_zip');
        $imgIndex = [];
        $zipDuplicates = [];
        $tmpDir = null;

        if ($zipFile && $zipFile->isValid() && strtolower($zipFile->getClientExtension()) === 'zip') {
            $maxZipMB = 100;
            if ($zipFile->getSizeByUnit('mb') > $maxZipMB) {
                return $this->response->setJSON($this->withCsrf(['error' => "ZIP terlalu besar (>{$maxZipMB} MB)"]))
                                      ->setStatusCode(413);
            }
            if (!class_exists(\ZipArchive::class)) {
                return $this->response->setJSON($this->withCsrf(['error' => 'ZipArchive tidak tersedia di server']))
                                      ->setStatusCode(500);
            }
            $tmpDir = WRITEPATH . 'tmp/bulk_images_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
            @mkdir($tmpDir, 0775, true);

            $zip = new \ZipArchive();
            if ($zip->open($zipFile->getTempName()) !== true) {
                return $this->response->setJSON($this->withCsrf(['error' => 'Gagal membuka file ZIP']))
                                      ->setStatusCode(422);
            }
            $zip->extractTo($tmpDir);
            $zip->close();

            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmpDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $f) {
                /** @var \SplFileInfo $f */
                if (!$f->isFile()) continue;
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;

                $base = pathinfo($f->getFilename(), PATHINFO_FILENAME);
                $key13 = $this->extractBmnKey($base); // 13 digit only
                if ($key13) {
                    if (!isset($imgIndex[$key13])) {
                        $imgIndex[$key13] = $f->getPathname();
                    } else {
                        $zipDuplicates[] = $f->getPathname(); // duplikat, abaikan
                    }
                }
            }
        }

        $results = [];
        $okCount = 0;

        foreach ($norm as $i => $row) {
            $rowNum = $i + 1;

            try {
                if ($row['nama'] === '') {
                    $results[] = ['row'=>$rowNum,'ok'=>false,'error'=>'Nama wajib diisi'];
                    continue;
                }

                $kapBTU = (int)preg_replace('/\D+/', '', (string)($row['kapasitas_btu'] ?? '12000'));
                if ($kapBTU <= 0) $kapBTU = 12000;

                $bmnDisp   = trim(preg_replace('/[^0-9.\-\s]+/u', '', (string)($row['bmn_no_display'] ?? '')));
                $bmnDigits = preg_replace('/\D+/', '', $bmnDisp);

                $freon = $this->toDecimal($row['tekanan_freon_terakhir'] ?? null);
                $amper = $this->toDecimal($row['amper_terakhir'] ?? null);

                $tglService = $this->toDateDdMmYyyyStrict($row['terakhir_service'] ?? null);
                $tglRawat   = $this->toDateDdMmYyyyStrict($row['terakhir_perawatan'] ?? null);

                $statusI = strtoupper(trim((string)($row['status'] ?: 'NORMAL')));
                $statusI = str_replace(' ', '_', $statusI);
                if (!in_array($statusI, $allowedStatus, true)) {
                    $statusI = $allowedStatus[0] ?? 'NORMAL';
                }

                $AC->resetQuery();
                $token = '';
                if ($hasKode) {
                    $token = bin2hex(random_bytes(16));
                    if ($AC->where('kode_qr', $token)->first()) {
                        $token = $this->makeKodeQrUnique($AC);
                    }
                }

                $nomorUnik = $this->normalizeName((string)$row['nama']);
                $nomorUnik = mb_substr($nomorUnik, 0, 64, 'UTF-8');
                if (in_array('nomor_unik', $uniques, true)) {
                    $nomorUnik = $this->makeUniqueValue($AC, 'nomor_unik', $nomorUnik, 64);
                }

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
                    'bmn_no_display' => ($bmnDisp !== '' ? $bmnDisp : null),
                    'status_ac'      => $statusI,
                    'tekanan_freon_terakhir' => $freon,
                    'amper_terakhir'         => $amper,
                    'terakhir_service'       => $tglService,
                    'terakhir_perawatan'     => $tglRawat,
                ];
                if ($hasSerial) $candidate['serial_no'] = ($serial !== '') ? $serial : null;

                $data = [];
                foreach ($candidate as $k => $v) if (isset($cols[$k])) $data[$k] = $v;

                if (isset($data['status_ac'], $cols['status_ac'])) {
                    $allowed = $this->parseEnumAllowed($cols['status_ac']['Type'] ?? null);
                    if ($allowed && !in_array($data['status_ac'], $allowed, true)) {
                        $data['status_ac'] = $allowed[0];
                    }
                }

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
                            'row'=>$rowNum,'ok'=>false,'error'=>'Gagal simpan (validasi model)',
                            'validation'=>$AC->errors()
                        ];
                        continue;
                    }
                } catch (DatabaseException $e) {
                    $results[] = ['row'=>$rowNum,'ok'=>false,'error'=>'DB exception: '.$e->getMessage()];
                    continue;
                }

                // FOTO BMN-only
                $imgSaved = false; 
                $usedKey = null;

                if (!empty($imgIndex)) {
                    // BMN harus 13 digit untuk bisa match
                    if ($bmnDigits !== '' && strlen($bmnDigits) === 13) {
                        if (isset($imgIndex[$bmnDigits])) {
                            $srcPath = $imgIndex[$bmnDigits];
                            $usedKey = $bmnDigits;
                            unset($imgIndex[$bmnDigits]);
                            $destDir = FCPATH.'uploads/ac_units/'.$id;
                            if (is_dir($destDir) || @mkdir($destDir, 0775, true) || is_dir($destDir)) {
                                $imgSaved = $this->processAndSaveImage($srcPath, $destDir);
                            }
                        }
                    }
                }

                $okCount++;
                $results[] = [
                    'row'   => $rowNum, 'ok' => true, 'id' => (int)$id,
                    'token' => $data['kode_qr'] ?? null,
                    'url'   => site_url('ac/'.($data['kode_qr'] ?? '')),
                    'foto'  => $imgSaved ? 'saved' : 'none',
                    'foto_key' => $usedKey,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'row'=>$rowNum,'ok'=>false,'error'=>'Row exception: '.$e->getMessage(),
                    'file'=>$e->getFile(),'line'=>$e->getLine(),
                ];
                continue;
            }
        }

        if ($tmpDir) $this->rrmdir($tmpDir);

        return $this->response->setJSON($this->withCsrf([
            'ok'       => true,
            'total'    => count($norm),
            'success'  => $okCount,
            'failed'   => count($norm) - $okCount,
            'skipped'  => $skippedNonArray,
            'results'  => $results,
            'zip_dup_count' => count($zipDuplicates),
        ]));
    }

    /* ============== Helpers ============== */

    private function firstNotEmpty(array $arr, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr)) {
                $v = trim((string)$arr[$k]);
                if ($v !== '') return $v;
            }
        }
        return null;
    }

    private function makeKodeQrUnique(AcUnitModel $AC, int $bytes = 16): string
    {
        for ($i = 0; $i < 5; $i++) {
            $t = bin2hex(random_bytes($bytes));
            if (!$AC->where('kode_qr', $t)->first()) return $t;
        }
        return bin2hex(random_bytes($bytes * 2));
    }

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

    /** Ambil BMN 13 digit dari string filename; kembalikan null jika tidak ada */
    private function extractBmnKey(string $s): ?string
    {
        if (preg_match('/(\d{13})/', $s, $m)) return $m[1];
        $digits = preg_replace('/\D+/', '', $s);
        if ($digits && strlen($digits) === 13) return $digits;
        return null;
    }

    private function toDecimal(?string $s): ?string
    {
        $s = trim((string)$s);
        if ($s === '') return null;
        if (preg_match('/-?\d+(?:[.,]\d+)?/', $s, $m)) {
            $num = str_replace(',', '.', $m[0]);
            $num = preg_replace('/\.(?=.*\.)/', '', $num);
            return $num;
        }
        return null;
    }

    // "DD-MM-YYYY" -> "Y-m-d"; invalid -> null
    private function toDateDdMmYyyyStrict(?string $s): ?string
    {
        $s = trim((string)$s);
        if ($s === '') return null;
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $s)) return null;

        $dt = \DateTime::createFromFormat('d-m-Y', $s);
        if (!$dt) return null;

        $errs = \DateTime::getLastErrors();
        if (is_array($errs)) {
            $warn = (int)($errs['warning_count'] ?? 0);
            $errc = (int)($errs['error_count'] ?? 0);
            if ($warn > 0 || $errc > 0) return null;
        }

        return $dt->format('Y-m-d');
    }

    // Resize+compress jpg (≤1600px), tolak >5MB or >12MP; EXIF safe
    private function processAndSaveImage(string $srcPath, string $destDir, int $maxW = 1600, int $maxH = 1600): bool
    {
        if (!is_file($srcPath)) return false;
        $size = @filesize($srcPath);
        if ($size !== false && $size > 5 * 1024 * 1024) return false;

        $info = @getimagesize($srcPath);
        if (!is_array($info) || count($info) < 3) return false;
        $w = (int)($info[0] ?? 0);
        $h = (int)($info[1] ?? 0);
        $type = (int)($info[2] ?? 0);
        if ($w <= 0 || $h <= 0 || $w * $h > 12000000) return false;

        switch ($type) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($srcPath); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($srcPath);  break;
            case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : null; break;
            default: $src = null;
        }
        if (!$src) return false;

        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $ort = 1;
            $exif = @exif_read_data($srcPath, null, true);
            if (is_array($exif)) {
                if (isset($exif['IFD0']['Orientation'])) $ort = (int)$exif['IFD0']['Orientation'];
                elseif (isset($exif['Orientation']))      $ort = (int)$exif['Orientation'];
            }
            if ($ort >= 2 && $ort <= 8) {
                $src = $this->applyExifOrientation($src, $ort);
                if (!$src) return false;
                $w = imagesx($src); $h = imagesy($src);
            }
        }

        $ratio = min($maxW / max(1,$w), $maxH / max(1,$h), 1.0);
        $nw = (int)round($w * $ratio);
        $nh = (int)round($h * $ratio);
        if ($nw <= 0 || $nh <= 0) { imagedestroy($src); return false; }

        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        foreach (['jpg','jpeg','png','webp'] as $x) @unlink($destDir.'/main.'.$x);
        $ok = @imagejpeg($dst, $destDir.'/main.jpg', 82);

        imagedestroy($dst);
        imagedestroy($src);
        return (bool)$ok;
    }

    private function applyExifOrientation($img, int $ort)
    {
        switch ($ort) {
            case 2: imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
            case 3: return imagerotate($img, 180, 0);
            case 4: imageflip($img, IMG_FLIP_VERTICAL); return $img;
            case 5: $img = imagerotate($img, -90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
            case 6: return imagerotate($img, -90, 0);
            case 7: $img = imagerotate($img, 90, 0); imageflip($img, IMG_FLIP_HORIZONTAL); return $img;
            case 8: return imagerotate($img, 90, 0);
            default: return $img;
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
