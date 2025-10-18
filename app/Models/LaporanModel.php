<?php
namespace App\Models;

use CodeIgniter\Model;

class LaporanModel extends Model
{
    protected $table         = 'laporan';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';

    // Sesuaikan dengan skema tabel kamu:
    protected $useTimestamps = false;   // set true kalau tabel punya created_at/updated_at
    protected $protectFields = false;   // sementara bebasin field, atau isi allowedFields kalau mau ketat

    // Kalau pakai soft delete di tabel 'laporan', buka ini:
    // protected $useSoftDeletes = true;
    // protected $deletedField   = 'deleted_at';

    /**
     * Hitung semua laporan, atau yang berstatus tertentu.
     * @param string|null $status null = semua
     */
    public function countByStatus(?string $status = null): int
    {
        $builder = $this->builder();              // respect soft-delete setting kalau diaktifkan
        if ($status !== null && $status !== '') {
            $builder->where('status', $status);   // ganti 'status' jika nama kolommu berbeda
        }
        return (int) $builder->countAllResults();
    }

    /**
     * Ambil jumlah per status (GROUP BY status).
     * Return: ['open'=>12,'closed'=>7,...]
     */
    public function countsGroupByStatus(): array
    {
        $rows = $this->select('status, COUNT(*) AS c')
                     ->groupBy('status')
                     ->findAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(string)($r['status'] ?? '')] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Ambil data terbaru untuk ditampilkan di dashboard/list.
     */
    public function recent(int $limit = 10): array
    {
        return $this->orderBy('id', 'DESC')
                    ->limit($limit)
                    ->find();
    }
}
