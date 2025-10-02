<?php

namespace Config;

use CodeIgniter\Config\Services;

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes = Services::routes();

/* Router setup */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

/* Load system routes */
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

/* ============== PUBLIC (TEKNISI via QR) ============== */
$routes->group('', static function ($routes) {
    $routes->get ('ac/(:segment)',           'Teknisi\Page::detailByToken/$1',          ['as' => 'teknisi.ac.detail']);
    $routes->get ('ac/(:segment)/perbaikan', 'Teknisi\Page::perbaikanByToken/$1',       ['as' => 'teknisi.ac.repair']);
    $routes->post('ac/(:segment)/perbaikan', 'Teknisi\Page::submitPerbaikanByToken/$1', ['as' => 'teknisi.ac.repair.submit']);

    $routes->get('teknisi/perbaikan', static function () {
        $t = service('request')->getGet('t');
        return $t ? redirect()->to(site_url('ac/'.rawurlencode($t).'/perbaikan'))
                  : redirect()->to(site_url('/'));
    });
});

/* ============== AUTH (PUBLIC) ============== */
$routes->get('login',    'Admin\Auth::login', ['as' => 'auth.login']);
$routes->post('auth/do', 'Admin\Auth::do',    ['as' => 'auth.do']);
$routes->get('auth/do', static fn() => redirect()->to(site_url('login')));
$routes->get('logout',   'Admin\Auth::logout', ['as' => 'auth.logout']);

/* ============== PROTECTED (LOGIN) ============== */
$routes->group('', ['filter' => 'auth'], static function ($routes) {

    /* -------- ADMIN ONLY -------- */
    $routes->group('', ['filter' => 'role:admin'], static function ($routes) {

        // Dashboard
        $routes->get('/',         'Admin\Dashboard::index', ['as' => 'admin.home']);
        $routes->get('dashboard', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);

        // Data Alat → AC
        $routes->group('admin/data-alat', static function ($routes) {
            $routes->group('ac', static function ($routes) {
                // List + Search (JSON)
                $routes->get ('/',            'Admin\AcUnits::index',  ['as' => 'admin.ac.index']);
                $routes->get ('search',       'Admin\AcUnits::search', ['as' => 'admin.ac.search']);

                // Show / Edit / Update / Delete
                $routes->get ('(:num)',       'Admin\AcUnits::show/$1',   ['as' => 'admin.ac.show']);
                $routes->get ('(:num)/edit',  'Admin\AcUnits::edit/$1',   ['as' => 'admin.ac.edit']);
                $routes->post('(:num)/save',  'Admin\AcUnits::update/$1', ['as' => 'admin.ac.update']);
                $routes->post('(:num)/delete','Admin\AcUnits::delete/$1', ['as' => 'admin.ac.delete']);

                // Tambah via QR Generator
                $routes->get ('tambah',       'Admin\Qr::index', ['as' => 'admin.ac.add']);
                $routes->post('tambah/save',  'Admin\Qr::save',  ['as' => 'admin.ac.add.save']);

                // Download ulang QR (PNG)
                $routes->get('(:num)/qr/download','Admin\AcUnits::downloadQr/$1', ['as' => 'admin.ac.qr.download']);
            });
        });

        // Render PNG QR untuk <img src="...">
        $routes->get('admin/qr/png/(:segment)', 'Admin\QrRender::show/$1', ['as' => 'admin.qr.show']);

        // Lain-lain (contoh)
        $routes->get('data_kendala',         'Admin\Data_kendala::data_kendala', ['as' => 'admin.data_kendala']);
        $routes->get('dashboard/chart-data', 'Admin\Dashboard::chartData',       ['as' => 'admin.chart_data']);
        $routes->get('notifications/latest', 'Admin\Notifications::latest',      ['as' => 'admin.notif.latest']);
        $routes->get('notifications/stream', 'Admin\Notifications::stream',      ['as' => 'admin.notif.stream']);

        // Pegawai (contoh tetap)
        $routes->get   ('pegawai',        'Admin\Employees::index',  ['as' => 'admin.emp.index']);
        $routes->get   ('pegawai/(:num)', 'Admin\Employees::show/$1');
        $routes->post  ('pegawai',        'Admin\Employees::store');
        $routes->put   ('pegawai/(:num)', 'Admin\Employees::update/$1');
        $routes->delete('pegawai/(:num)', 'Admin\Employees::delete/$1');
        $routes->get   ('pegawai/search', 'Admin\Employees::search');

        // QR lama (opsional)
        $routes->get ('admin/qr',      'Admin\Qr::index', ['as' => 'admin.qr']);
        $routes->post('admin/qr/save', 'Admin\Qr::save',  ['as' => 'admin.qr.save']);
        if (ENVIRONMENT !== 'production') {
            $routes->get('admin/qr/diag', 'Admin\Qr::diag', ['as' => 'admin.qr.diag']);
        }
    });
});

/* Per-environment */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
