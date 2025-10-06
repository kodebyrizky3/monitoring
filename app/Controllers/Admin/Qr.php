<?php
namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class Qr extends BaseController
{
    public function index()
    {
        return view('Admin/qr/index', [
            'title'  => 'Generate QR',
            'activeMenu' => 'qr',
        ]);
    }
}
