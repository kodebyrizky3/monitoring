<?php
namespace App\Controllers\Teknisi;

use App\Controllers\BaseController;
use App\Models\AcUnitModel;
use App\Models\AcTicketModel;
use App\Models\AcRepairModel;

class Page extends BaseController
{
    /** Cek kolom ada/tidak */
    private function hasColumn(string $table, string $column): bool
    {
        $db = \Config\Database::connect();
        $row = $db->query("
            SELECT 1
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = ?
              AND COLUMN_NAME  = ?
            LIMIT 1
        ", [$table, $column])->getRowArray();
        return (bool)$row;
    }

    /** Ambil nama kolom pertama yang tersedia dari kandidat */
    private function firstExistingCol(string $table, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            if ($this->hasColumn($table, $c)) return $c;
        }
        return null;
    }

    public function detailByToken(string $token)
    {
        $token = trim($token);
        $AC = new AcUnitModel();

        // cari by kode_qr → fallback nomor_unik
        $ac = $AC->where('kode_qr', $token)->first();
        if (!$ac) $ac = $AC->where('nomor_unik', $token)->first();

        $wantsJson = ($this->request->getGet('format') === 'json') || $this->request->isAJAX();
        if ($wantsJson) {
            if (!$ac) {
                return $this->response->setJSON(['ok'=>false,'error'=>'Perangkat tidak ditemukan'])->setStatusCode(404);
            }

            // foto utama
            $fotoUrl = null;
            $dir = FCPATH.'uploads/ac_units/'.$ac['id'].'/';
            if (is_dir($dir)) {
                foreach (['jpg','jpeg','png','webp'] as $ext) {
                    $f = $dir.'main.'.$ext;
                    if (is_file($f)) { $fotoUrl = site_url('uploads/ac_units/'.$ac['id'].'/main.'.$ext); break; }
                }
            }

            // tiket aktif
            $T = new AcTicketModel();
            $ticket = $T->where('ac_id', $ac['id'])
                        ->whereNotIn('status_tiket', ['SELESAI','DITOLAK_ADMIN'])
                        ->orderBy('updated_at','desc')->orderBy('created_at','desc')
                        ->first();

            // last_service_at: pakai kolom jika ada; fallback dari ac_repairs (submitted_at max)
            $lastServiceAt = $ac['last_service_at'] ?? null;
            if (!$lastServiceAt) {
                $R = new AcRepairModel();
                $rp = $R->select('submitted_at')
                        ->where('ac_id', $ac['id'])
                        ->orderBy('submitted_at','desc')
                        ->first();
                $lastServiceAt = $rp['submitted_at'] ?? null;
            }

            // last_perawatan_at: kalau punya kolom itu di ac_units
            $lastPerawatanAt = null;
            if ($this->hasColumn($AC->table, 'last_perawatan_at')) {
                $lastPerawatanAt = $ac['last_perawatan_at'] ?? null;
            }

            // ===== Tekanan Freon & Ampere Terakhir (fleksibel) =====
            // 1) coba ambil dari ac_units (nama kolom umum yang mungkin dipakai)
            $freonColUnits = $this->firstExistingCol($AC->table, [
                'last_freon', 'last_freon_psi', 'tekanan_freon_terakhir', 'freon_terakhir'
            ]);
            $amperColUnits = $this->firstExistingCol($AC->table, [
                'last_amper', 'last_ampere', 'arus_terakhir', 'ampere_terakhir'
            ]);

            $lastFreon = $freonColUnits ? ($ac[$freonColUnits] ?? null) : null;
            $lastAmper = $amperColUnits ? ($ac[$amperColUnits] ?? null) : null;

            // 2) kalau masih kosong, fallback dari ac_repairs (kolom yang mungkin ada)
            $R = new AcRepairModel();
            $repairsTable = $R->table;

            if ($lastFreon === null) {
                $freonColsRep = array_values(array_filter([
                    $this->hasColumn($repairsTable, 'tekanan_freon') ? 'tekanan_freon' : null,
                    $this->hasColumn($repairsTable, 'freon_psi') ? 'freon_psi' : null,
                    $this->hasColumn($repairsTable, 'tekanan_psi') ? 'tekanan_psi' : null,
                ]));
                if (!empty($freonColsRep)) {
                    $sel = implode(',', array_map(static fn($c) => "`$c`", $freonColsRep)).', submitted_at';
                    $rp = $R->select($sel)->where('ac_id', $ac['id'])->orderBy('submitted_at','desc')->first();
                    if ($rp) {
                        foreach ($freonColsRep as $c) {
                            if (isset($rp[$c]) && $rp[$c] !== '' && $rp[$c] !== null) { $lastFreon = $rp[$c]; break; }
                        }
                    }
                }
            }

            if ($lastAmper === null) {
                $amperColsRep = array_values(array_filter([
                    $this->hasColumn($repairsTable, 'amper') ? 'amper' : null,
                    $this->hasColumn($repairsTable, 'ampere') ? 'ampere' : null,
                    $this->hasColumn($repairsTable, 'arus') ? 'arus' : null,
                    $this->hasColumn($repairsTable, 'arus_ampere') ? 'arus_ampere' : null,
                ]));
                if (!empty($amperColsRep)) {
                    $sel = implode(',', array_map(static fn($c) => "`$c`", $amperColsRep)).', submitted_at';
                    $rp = $R->select($sel)->where('ac_id', $ac['id'])->orderBy('submitted_at','desc')->first();
                    if ($rp) {
                        foreach ($amperColsRep as $c) {
                            if (isset($rp[$c]) && $rp[$c] !== '' && $rp[$c] !== null) { $lastAmper = $rp[$c]; break; }
                        }
                    }
                }
            }

            return $this->response->setJSON([
                'ok' => true,
                'ac' => [
                    'id'               => (int)$ac['id'],
                    'nomor_unik'       => $ac['nomor_unik'] ?? null,
                    'kode_qr'          => $ac['kode_qr'] ?? null,
                    'tipe_model'       => $ac['tipe_model'] ?? null,
                    'kapasitas_btu'    => $ac['kapasitas_btu'] ?? null,
                    'lokasi'           => $ac['lokasi'] ?? null,
                    'status_ac'        => $ac['status_ac'] ?? 'NORMAL',
                    'serial_no'        => $ac['serial_no'] ?? null,
                    'bmn_no_display'   => $ac['bmn_no_display'] ?? null,
                    'last_perawatan_at'=> $lastPerawatanAt,
                    'last_service_at'  => $lastServiceAt,
                    'last_freon'       => $lastFreon,   // ← baru
                    'last_amper'       => $lastAmper,   // ← baru
                    'foto_url'         => $fotoUrl,
                ],
                'ticket_id' => $ticket['id'] ?? null,
            ]);
        }

        return view('teknisi/ac_detail', [
            'title' => $ac['nomor_unik'] ?? 'Perangkat',
            'token' => $token,
        ]);
    }

    public function perbaikanByToken(string $token)
    {
        $token = trim($token);
        return view('teknisi/perbaikan', [
            'title' => 'Laporan Perbaikan',
            'token' => $token,
        ]);
    }

    public function submitPerbaikanByToken(string $token)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->response->setJSON(['error'=>'Method Not Allowed'])->setStatusCode(405);
        }

        $token = trim($token);
        $AC = new AcUnitModel();
        $ac = $AC->where('kode_qr', $token)->first();
        if (!$ac) return $this->response->setJSON(['error'=>'Perangkat tidak ditemukan'])->setStatusCode(404);

        $rules = [
            'teknisi_nama'     => 'required|string|min_length[3]|max_length[120]',
            'tindakan'         => 'required|string|min_length[3]',
            'hasil_perbaikan'  => 'required|string|min_length[3]',
            'biaya'            => 'permit_empty|decimal',
            'ticket_id'        => 'permit_empty|integer',
        ];
        if (! $this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => implode(' ', $this->validator->getErrors())
            ]);
        }

        $teknisiNama    = trim((string)$this->request->getPost('teknisi_nama'));
        $tindakan       = trim((string)$this->request->getPost('tindakan'));
        $hasilPerbaikan = trim((string)$this->request->getPost('hasil_perbaikan'));
        $part           = trim((string)$this->request->getPost('part'));
        $biayaRaw       = $this->request->getPost('biaya');
        $biaya          = ($biayaRaw !== null && $biayaRaw !== '') ? (float)$biayaRaw : null;

        $T = new AcTicketModel();
        $ticket = null;
        $ticketIdPost = (int)($this->request->getPost('ticket_id') ?? 0);
        if ($ticketIdPost > 0) {
            $ticket = $T->where('id',$ticketIdPost)->where('ac_id',$ac['id'])->first();
        }
        if (!$ticket) {
            $ticket = $T->where('ac_id', $ac['id'])
                        ->whereNotIn('status_tiket', ['SELESAI','DITOLAK_ADMIN'])
                        ->orderBy('updated_at','desc')->orderBy('created_at','desc')
                        ->first();
        }
        if (!$ticket) {
            return $this->response->setStatusCode(422)->setJSON(['error'=>'Tidak ada tiket aktif. Buat/approve tiket dulu.']);
        }

        // upload foto after (opsional)
        $afterPath = null;
        $foto = $this->request->getFile('fotoAfter');
        if ($foto && $foto->isValid()) {
            $dir = FCPATH.'uploads/ac_units/'.$ac['id'].'/repairs';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return $this->response->setJSON(['error'=>'Gagal buat folder upload'])->setStatusCode(500);
            }
            $ext = strtolower($foto->getClientExtension() ?: $foto->getExtension() ?: $foto->guessExtension() ?: 'jpg');
            if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) $ext = 'jpg';
            $fname = 'after_'.date('Ymd_His').'.'.$ext;
            $foto->move($dir, $fname, true);
            $afterPath = '/uploads/ac_units/'.$ac['id'].'/repairs/'.$fname;
        }

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            (new \App\Models\AcRepairModel())->insert([
                'ticket_id'        => (int)$ticket['id'],
                'ac_id'            => (int)$ac['id'],
                'teknisi_nama'     => $teknisiNama,
                'tindakan'         => $tindakan,
                'hasil_perbaikan'  => $hasilPerbaikan,
                'foto_sesudah'     => $afterPath ? site_url(ltrim($afterPath,'/')) : null,
                'biaya'            => $biaya,
                'submitted_at'     => date('Y-m-d H:i:s'),
                'verifikasi_status'=> 'MENUNGGU_ADMIN',
            ]);

            $T->update($ticket['id'], [
                'status_tiket' => 'MENUNGGU_VERIFIKASI',
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $append = "Perbaikan: ".$tindakan;
            if ($part !== '')  $append .= " | Part: ".$part;
            if ($biaya !== null && $biaya > 0) $append .= " | Biaya: ".$biaya;
            $newNote = trim(($ac['catatan'] ?? '')."\n".$append);

            $AC->update($ac['id'], ['status_ac' => 'NORMAL','catatan' => $newNote]);

            $db->transComplete();
            if (! $db->transStatus()) throw new \RuntimeException('Transaksi gagal');

            return $this->response->setJSON([
                'ok'        => true,
                'message'   => 'Perbaikan disimpan & tiket menunggu verifikasi admin.',
                'fotoAfter' => $afterPath ? site_url(ltrim($afterPath,'/')) : null,
                'redirect'  => site_url('ac/'.$token),
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['error'=>$e->getMessage()]);
        }
    }
}
