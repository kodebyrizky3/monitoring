<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Kendala extends BaseController
{
    protected function jsonOk($data = [], $message = 'OK')
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'csrf'    => csrf_hash(),
        ] + $data);
    }

    protected function jsonFail($message = 'Gagal', $code = 400, $errors = [])
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'csrf'    => csrf_hash(),
        ]);
    }

    public function index()
    {
        return view('Admin/kendala/index', [
            'title'      => 'Data Kendala',
            'activeMenu' => 'data-kendala',
        ]);
    }

    /**
     * GET /admin/kendala/search?q=&module=&status=&page=&perPage=
     * sumber: vw_admin_kendala
     */
    public function search()
    {
        /** @var BaseConnection $db */
        $db = \Config\Database::connect();

        $q       = trim((string) ($this->request->getGet('q') ?? ''));
        $module  = trim((string) ($this->request->getGet('module') ?? 'SEMUA'));
        $status  = trim((string) ($this->request->getGet('status') ?? 'SEMUA'));
        $perPage = max((int) ($this->request->getGet('perPage') ?? 10), 1);
        $page    = max((int) ($this->request->getGet('page') ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $builder = $db->table('vw_admin_kendala');

        if ($module !== '' && $module !== 'SEMUA') {
            $builder->where('module', $module);
        }
        if ($status !== '' && $status !== 'SEMUA') {
            $builder->where('status_norm', $status);
        }
        if ($q !== '') {
            $builder->groupStart()
                ->like('subject', $q)
                ->orLike('detail',  $q)
                ->groupEnd();
        }

        // total
        $countBuilder = clone $builder;
        $total = (int) $countBuilder->select('COUNT(*) AS c')->get()->getRow('c');

        // data
        $rows = $builder
            ->select('module,item_type,item_id,kendaraan_id,subject,detail,status_norm,created_at')
            ->orderBy('created_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()->getResultArray();

        return $this->jsonOk([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'pageCount' => (int) ceil(max($total, 1) / $perPage),
        ]);
    }

    // ===== Detail: ticket (kerusakan kendaraan)
        public function detailTicket($id)
    {
        $db = \Config\Database::connect();

        // Ambil tiket + info kendaraan yang tersedia di tabel kamu
        $row = $db->table('kendaraan_tickets t')
            ->select('
                t.id, t.kendaraan_id, t.pelapor_nama_snapshot, t.deskripsi_keluhan, t.foto_keluhan,
                t.status_tiket, t.approved_by, t.approved_at, t.created_at, t.updated_at,
                ku.no_polisi, ku.merk_model, ku.tipe, ku.tahun
            ')
            ->join('kendaraan_units ku', 'ku.id = t.kendaraan_id', 'left')
            ->where('t.id', (int)$id)
            ->get()->getRowArray();

        if (!$row) {
            return $this->jsonFail('Ticket tidak ditemukan', 404);
        }

        // Normalisasi status -> status_norm
        $statusNorm = match ($row['status_tiket']) {
            'MENUNGGU_ADMIN' => 'PENDING',
            'DISETUJUI'      => 'APPROVED',
            'DITOLAK'        => 'REJECTED',
            default          => strtoupper((string)$row['status_tiket']),
        };

        // Foto (optional)
        $photos = [];
        if (!empty($row['foto_keluhan'])) {
            $photos[] = [
                'url'     => base_url($row['foto_keluhan']),
                'caption' => 'Foto keluhan',
            ];
        }

        // Label kendaraan yang enak dibaca
        $parts = array_filter([
            $row['no_polisi'] ?? null,
            trim(($row['merk_model'] ?? '') . ' ' . ($row['tipe'] ?? '')) ?: null,
            isset($row['tahun']) && $row['tahun'] !== '' ? '(' . $row['tahun'] . ')' : null,
        ]);
        $kendaraanLabel = implode(' • ', $parts);

        return $this->jsonOk([
            'type'   => 'ticket',       // kerusakan kendaraan
            'module' => 'kendaraan',
            'data'   => [
                'id'          => (int)$row['id'],
                'kendaraan'   => $kendaraanLabel !== '' ? $kendaraanLabel : '-',
                'subject'     => 'Laporan Kerusakan',
                'detail'      => (string)($row['deskripsi_keluhan'] ?? ''),
                'status_norm' => $statusNorm,
                'raw_status'  => (string)($row['status_tiket'] ?? ''),
                'created_at'  => (string)$row['created_at'],
                'updated_at'  => (string)$row['updated_at'],
                'pelapor'     => (string)($row['pelapor_nama_snapshot'] ?? ''),
                'photos'      => $photos,
            ],
        ]);
    }


    // ===== Detail: service (stub; sesuaikan jika tabelnya ada)
    public function detailService($id)
    {
        return $this->jsonFail('Service request tidak ditemukan / belum diimplementasi', 404);
    }

    // ===== Actions
    public function approveTicket($id)
    {
        return $this->updateTicketStatus((int)$id, 'DISETUJUI');  // sesuaikan nilai enum kamu
    }

    public function rejectTicket($id)
    {
        return $this->updateTicketStatus((int)$id, 'DITOLAK');    // sesuaikan nilai enum kamu
    }

    public function approveService($id)
    {
        return $this->jsonFail('Belum diimplementasi', 501);
    }

    public function rejectService($id)
    {
        return $this->jsonFail('Belum diimplementasi', 501);
    }

    protected function updateTicketStatus(int $id, string $to)
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->jsonFail('Method tidak diizinkan', 405);
        }
        $adminId = (int) (session('user_id') ?? 0);

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $row = $db->table('kendaraan_tickets')->select('id,status_tiket')->where('id', $id)->get()->getRowArray();
            if (!$row) return $this->jsonFail('Ticket tidak ditemukan', 404);
            if ($row['status_tiket'] !== 'MENUNGGU_ADMIN') return $this->jsonFail('Status bukan MENUNGGU_ADMIN', 422);

            $db->table('kendaraan_tickets')->where('id', $id)->update([
                'status_tiket' => $to,
                'approved_by'  => $adminId ?: null,
                'approved_at'  => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
            if ($db->transStatus() === false) throw new DatabaseException('Transaksi gagal');

            return $this->jsonOk([], 'Berhasil diperbarui');
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->jsonFail('Gagal memperbarui: '.$e->getMessage(), 500);
        }
    }

    public function export()
    {
        return $this->jsonFail('Belum diimplementasi', 501);
    }
}