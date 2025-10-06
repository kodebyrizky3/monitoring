<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class Kendala extends BaseController
{
    /* ======================= Helpers JSON ======================= */
    protected function jsonOk(array $data = [], string $message = 'OK')
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => $message,
            'csrf'    => csrf_hash(),
        ] + $data);
    }
    protected function jsonFail(string $message = 'Gagal', int $code = 400, array $errors = [])
    {
        return $this->response->setStatusCode($code)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'csrf'    => csrf_hash(),
        ]);
    }

    /* ======================= View Index ======================= */
    public function index()
    {
        return view('Admin/kendala/index', [
            'title'      => 'Data Kendala',
            'activeMenu' => 'data-kendala',
        ]);
    }

    /* ======================= List/Search (vw_admin_kendala) ======================= */
    // GET /admin/kendala/search?q=&module=&status=&page=&perPage=
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

        $b = $db->table('vw_admin_kendala');

        if ($module !== '' && $module !== 'SEMUA') $b->where('module', $module);
        if ($status !== '' && $status !== 'SEMUA') $b->where('status_norm', $status);
        if ($q !== '') {
            $b->groupStart()
              ->like('subject', $q)
              ->orLike('detail',  $q)
              ->groupEnd();
        }

        $total = (int) (clone $b)->select('COUNT(*) c')->get()->getRow('c');
        $rows  = $b->select('module,item_type,item_id,kendaraan_id,subject,detail,status_norm,created_at')
                   ->orderBy('created_at','DESC')->limit($perPage,$offset)->get()->getResultArray();

        return $this->jsonOk([
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'perPage'   => $perPage,
            'pageCount' => (int) ceil(max($total, 1) / $perPage),
        ]);
    }

    /* ======================= DETAIL: Ticket (kerusakan) ======================= */
    // GET /admin/kendala/ticket/{id}
    public function detailTicket($id)
    {
        $db = \Config\Database::connect();
        $row = $db->table('kendaraan_tickets t')
            ->select('
                t.id, t.kendaraan_id, t.pelapor_nama_snapshot, t.deskripsi_keluhan, t.foto_keluhan,
                t.status_tiket, t.approved_by, t.approved_at, t.created_at, t.updated_at,
                ku.no_polisi, ku.merk_model, ku.tipe, ku.tahun
            ')
            ->join('kendaraan_units ku','ku.id = t.kendaraan_id','left')
            ->where('t.id',(int)$id)->get()->getRowArray();

        if (!$row) return $this->jsonFail('Ticket tidak ditemukan', 404);

        // Map status -> status_norm
        $statusNorm = match ($row['status_tiket']) {
            'MENUNGGU_ADMIN' => 'PENDING',
            'DISETUJUI', 'DISETUJUI_ADMIN' => 'APPROVED',
            'DITOLAK', 'DITOLAK_ADMIN'     => 'REJECTED',
            default                         => strtoupper((string)$row['status_tiket']),
        };

        $photos = [];
        if (!empty($row['foto_keluhan'])) {
            $photos[] = [ 'url' => base_url($row['foto_keluhan']), 'caption' => 'Foto keluhan' ];
        }

        $kendaraanLabel = implode(' • ', array_filter([
            $row['no_polisi'] ?? null,
            trim(($row['merk_model'] ?? '').' '.($row['tipe'] ?? '')) ?: null,
            (isset($row['tahun']) && $row['tahun'] !== '') ? '(' . $row['tahun'] . ')' : null,
        ]));

        return $this->jsonOk([
            'type'   => 'ticket',
            'module' => 'kendaraan',
            'data'   => [
                'id'          => (int) $row['id'],
                'kendaraan'   => $kendaraanLabel ?: '-',
                'subject'     => 'Laporan Kerusakan',
                'detail'      => (string) ($row['deskripsi_keluhan'] ?? ''),
                'status_norm' => $statusNorm,
                'raw_status'  => (string) ($row['status_tiket'] ?? ''),
                'created_at'  => (string) $row['created_at'],
                'updated_at'  => (string) $row['updated_at'],
                'pelapor'     => (string) ($row['pelapor_nama_snapshot'] ?? ''),
                'photos'      => $photos,
            ],
        ]);
    }

    /* ======================= DETAIL: Service (stub) ======================= */
    // GET /admin/kendala/service/{id}
    public function detailService($id)
    {
        return $this->jsonFail('Service request tidak ditemukan / belum diimplementasi', 404);
    }

    /* ======================= ACTIONS: Ticket ======================= */
    // POST /admin/kendala/ticket/{id}/approve
    public function approveTicket($id)
    {
        return $this->updateTicketStatus((int)$id, 'DISETUJUI_ADMIN');
    }
    // POST /admin/kendala/ticket/{id}/reject
    public function rejectTicket($id)
    {
        return $this->updateTicketStatus((int)$id, 'DITOLAK_ADMIN');
    }

    protected function updateTicketStatus(int $id, string $to)
    {
        if ($this->request->getMethod(true) !== 'POST') {
            return $this->jsonFail('Method tidak diizinkan', 405);
        }        
        $adminId = $this->adminId();

        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $row = $db->table('kendaraan_tickets')->select('id,status_tiket')->where('id',$id)->get()->getRowArray();
            if (!$row) return $this->jsonFail('Ticket tidak ditemukan', 404);
            if ($row['status_tiket'] !== 'MENUNGGU_ADMIN') return $this->jsonFail('Status bukan MENUNGGU_ADMIN', 422);

            $db->table('kendaraan_tickets')->where('id',$id)->update([
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

    /* ======================= ACTIONS: Service (opsional) ======================= */
    public function approveService($id) { return $this->updateServiceStatus((int)$id, 'DISETUJUI_ADMIN'); }
    public function rejectService($id)  { return $this->updateServiceStatus((int)$id, 'DITOLAK'); }

    protected function updateServiceStatus(int $id, string $to)
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->jsonFail('Method tidak diizinkan', 405);
        }
        $db = \Config\Database::connect();
        $db->transStart();
        try {
            $row = $db->table('kendaraan_services')->select('id,status_servis')->where('id',$id)->get()->getRowArray();
            if (!$row) return $this->jsonFail('Service tidak ditemukan', 404);
            if ($row['status_servis'] !== 'DIAJUKAN') return $this->jsonFail('Status bukan DIAJUKAN', 422);

            $db->table('kendaraan_services')->where('id',$id)->update([
                'status_servis' => $to,
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);

            $db->transComplete();
            if ($db->transStatus() === false) throw new DatabaseException('Transaksi gagal');

            return $this->jsonOk([], 'Berhasil diperbarui');
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->jsonFail('Gagal memperbarui: '.$e->getMessage(), 500);
        }
    }

    /* ======================= Utils ======================= */
    private function adminId(): int
    {
        helper('auth');
        if (function_exists('user') && user()) return (int) (user()->id ?? 0);
        return (int) (session('user_id') ?? 0);
    }

    /* ======================= Export (stub) ======================= */
    public function export()
    {
        return $this->jsonFail('Belum diimplementasi', 501);
    }
}
