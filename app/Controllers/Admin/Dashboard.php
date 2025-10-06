<?php

namespace App\Controllers\Admin;

use App\Models\LaporanModel;
use App\Models\AlatModel;
use CodeIgniter\HTTP\ResponseInterface;
use App\Controllers\BaseController;

class Dashboard extends BaseController
{
    public function index()
    {
        $alat = new AlatModel();
        $lap  = new LaporanModel();

        $data = [
            'title'           => 'Dashboard',
            'activeMenu'      => 'dashboard',

            // KPI baru (ganti Total User & Teknisi Aktif)
            'totalAlat'       => $alat->total(),
            'totalAC'         => $alat->byTipe('AC'),
            'totalKendaraan'  => $alat->byTipe('KENDARAAN'),
            'pendingVerif'    => $lap->countByStatus('baru'),

            // dua kartu lain (opsional)
            'perluPerbaikan'  => $lap->countPerluPerbaikan(),
            'selesaiBulanIni' => $lap->countSelesaiBulanIni(),

            // existing
            'totalLaporan'    => $lap->countSemua(),
            'latest'          => $lap->latest(5),
        ];

        return view('Admin/dashboard/index', $data);
    }


    public function chartData()
    {
        $year   = (int) date('Y');
        $labels = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

        $lap = new \App\Models\LaporanModel();
        $series = $lap->monthlyKendala($year);

        return $this->response->setJSON([
            'labels' => $labels,
            'data'   => $series,
            'year'   => $year,
        ]);
    }

}
