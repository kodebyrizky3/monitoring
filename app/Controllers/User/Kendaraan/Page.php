<?php
namespace App\Controllers\User\Kendaraan;

use App\Controllers\BaseController;

class Page extends BaseController
{
    public function index()
    {
        return view('User/kendaraan/index', [
            'title' => 'Kendaraan',
        ]);
    }
}
