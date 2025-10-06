<?php

namespace App\Controllers;

use App\Models\LaporanModel;

class Notifications extends BaseController
{
    public function latest()
    {
        $lap = new LaporanModel();
        $items = $lap->latest(5);
        return $this->response->setJSON([
            'count' => $lap->countBaru(),
            'items' => array_map(function($r){
                return [
                    'id'        => $r['id'],
                    'judul'     => $r['judul'] ?? 'Laporan Baru',
                    'created_at'=> $r['created_at'],
                    'status'    => $r['status'],
                    'tipe'      => $r['tipe_alat'] ?? '-',
                ];
            }, $items ?? [])
        ]);
    }

    // SSE: stream notifikasi baru (demo; sederhana)
    public function stream()
    {
        $response = $this->response;
        $response->setHeader('Content-Type', 'text/event-stream');
        $response->setHeader('Cache-Control', 'no-cache');
        $response->setHeader('Connection', 'keep-alive');

        // batas wajar agar tidak menahan proses terlalu lama (demo 60 detik)
        $start = time();
        $lap   = new LaporanModel();

        while (time() - $start < 60) {
            $count = $lap->countBaru();
            $items = $lap->latest(5);

            $payload = json_encode([
                'count' => $count,
                'items' => array_map(function($r){
                    return [
                        'id'    => $r['id'],
                        'judul' => $r['judul'] ?? 'Laporan Baru',
                        'status'=> $r['status'],
                        'waktu' => $r['created_at'],
                    ];
                }, $items ?? [])
            ]);

            echo "event: laporan\n";
            echo "data: {$payload}\n\n";
            @ob_flush();
            @flush();

            sleep(3); // cek tiap 3 detik
        }

        return;
    }
}
