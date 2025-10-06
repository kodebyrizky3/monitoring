<?php

namespace App\Models;

use CodeIgniter\Model;

class LaporanModel extends Model
{
    protected $table         = 'laporan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'alat_id',
        'judul',
        'deskripsi',
        'pelapor_nama',
        'pelapor_kontak',
        'status',        // baru | diverifikasi | diproses | selesai | ditolak
        'verifikasi_by', // (optional) id user admin
        'verifikasi_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /* ===========================
     *        COUNTERS
     * =========================== */

    /** Jumlah laporan status 'baru' (untuk notifikasi) */
    public function countBaru(): int
    {
        return $this->where('status', 'baru')->countAllResults(true);
    }

    /** Total semua laporan (tanpa kondisi) */
    public function countSemua(): int
    {
        return $this->builder()->countAll(); // aman dari sticky where
    }

    /** Hitung berdasarkan 1 status tertentu */
    public function countByStatus(string $status): int
    {
        return $this->where('status', $status)->countAllResults(true);
    }

    /** Hitung laporan yang perlu ditindak (diverifikasi/diproses) */
    public function countPerluPerbaikan(): int
    {
        return $this->whereIn('status', ['diverifikasi', 'diproses'])->countAllResults(true);
    }

    /** Hitung laporan selesai pada bulan berjalan */
    public function countSelesaiBulanIni(): int
    {
        $start = date('Y-m-01 00:00:00');
        $end   = date('Y-m-t 23:59:59');

        return $this->where('status', 'selesai')
            ->where('created_at >=', $start)
            ->where('created_at <=', $end)
            ->countAllResults(true);
    }

    /* ===========================
     *        LIST/AGGREGATE
     * =========================== */

    /** 5 aktivitas terbaru (bisa ubah limit) */
    public function latest(int $limit = 5): array
    {
        // gunakan builder() agar tidak “lengket” dengan kondisi sebelumnya
        return $this->builder()
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Data agregasi per bulan (12 nilai) untuk chart.
     * Default: tahun saat ini jika $year null.
     */
    public function monthlyKendala(?int $year = null): array
    {
        $year = $year ?: (int) date('Y');

        $rows = $this->builder()
            ->select('MONTH(created_at) AS m, COUNT(*) AS total')
            ->where('status <>', 'ditolak')
            ->where('created_at >=', "{$year}-01-01 00:00:00")
            ->where('created_at <=', "{$year}-12-31 23:59:59")
            ->groupBy('m')
            ->get()
            ->getResultArray();

        // Normalisasi 1..12
        $data = array_fill(1, 12, 0);
        foreach ($rows as $r) {
            $month = (int) ($r['m'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $data[$month] = (int) $r['total'];
            }
        }

        return array_values($data); // [Jan..Des]
    }
}
