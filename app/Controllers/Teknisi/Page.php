<?php
namespace App\Controllers\Teknisi;

use App\Controllers\BaseController;

class Page extends BaseController
{
    public function detailByToken(string $token)
    {
        return view('teknisi/ac_detail', [
            'title' => 'Detail AC • Teknisi',
            'token' => $token,
        ]);
    }

    public function perbaikanByToken(string $token)
    {
        return view('teknisi/perbaikan', [
            'title' => 'Laporan Perbaikan • Teknisi',
            'token' => $token,
        ]);
    }
}
