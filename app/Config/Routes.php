<?php

namespace Config;

use CodeIgniter\Config\Services;

/** @var \CodeIgniter\Router\RouteCollection $routes */
$routes = Services::routes();

/* -----------------------------
 * Router Setup (global)
 * ----------------------------- */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

/* -----------------------------
 * Load the system routes first
 * ----------------------------- */
if (is_file(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

/* =========================
 * PUBLIC: TEKNISI via TOKEN (QR)
 * ========================= */
$routes->group('', static function ($routes) {
    $routes->get ('ac/(:segment)',           'Teknisi\Page::detailByToken/$1',          ['as' => 'teknisi.ac.detail']);
    $routes->get ('ac/(:segment)/perbaikan', 'Teknisi\Page::perbaikanByToken/$1',       ['as' => 'teknisi.ac.repair']);
    $routes->post('ac/(:segment)/perbaikan', 'Teknisi\Page::submitPerbaikanByToken/$1', ['as' => 'teknisi.ac.repair.submit']);

    $routes->get('teknisi/perbaikan', static function () {
        $t = service('request')->getGet('t');
        return $t
            ? redirect()->to(site_url('ac/' . rawurlencode($t) . '/perbaikan'))
            : redirect()->to(site_url('/'));
    });
});

/* =========================
 * AUTH (PUBLIC)
 * ========================= */
$routes->get ('login',   'Admin\Auth::login', ['as' => 'auth.login']);
$routes->post('auth/do', 'Admin\Auth::do',    ['as' => 'auth.do']);
$routes->get ('auth/do', static fn () => redirect()->to(site_url('login'))); // fallback GET
$routes->get ('logout',  'Admin\Auth::logout', ['as' => 'auth.logout']);

/* =========================
 * PROTECTED AREA (must login)
 * ========================= */
$routes->group('', ['filter' => 'auth'], static function ($routes) {

    /* ---------- ADMIN ONLY ---------- */
    $routes->group('', ['filter' => 'role:admin'], static function ($routes) {

        // Dashboard
        $routes->get('/',         'Admin\Dashboard::index', ['as' => 'admin.home']);
        $routes->get('dashboard', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);

        // Chart & Notifikasi
        $routes->get('dashboard/chart-data', 'Admin\Dashboard::chartData',  ['as' => 'admin.chart_data']);
        $routes->get('notifications/latest', 'Admin\Notifications::latest', ['as' => 'admin.notif.latest']);
        $routes->get('notifications/stream', 'Admin\Notifications::stream', ['as' => 'admin.notif.stream']);
        $routes->get('data_kendala', 'Admin\Kendala::index', ['as' => 'admin.data_kendala']);

        // =========================
        // ADMIN: Data Alat → AC
        // =========================
        $routes->group('admin/data-alat', static function ($routes) {
            $routes->group('ac', static function ($routes) {
                $routes->get ('/',            'Admin\AcUnits::index',  ['as' => 'admin.ac.index']);
                $routes->get ('search',       'Admin\AcUnits::search', ['as' => 'admin.ac.search']);
                $routes->get ('(:num)',        'Admin\AcUnits::show/$1',   ['as' => 'admin.ac.show']);
                $routes->get ('(:num)/edit',   'Admin\AcUnits::edit/$1',   ['as' => 'admin.ac.edit']);
                $routes->post('(:num)/save',   'Admin\AcUnits::update/$1', ['as' => 'admin.ac.update']);
                $routes->post('(:num)/delete', 'Admin\AcUnits::delete/$1', ['as' => 'admin.ac.delete']);
                $routes->post('admin/data-alat/ac/bulk-delete', 'Admin\AcUnits::bulkDelete', ['as' => 'admin.ac.bulk_delete']);

                // Tambah via QR Generator
                $routes->get ('tambah',      'Admin\Qr::index', ['as' => 'admin.ac.add']);
                $routes->post('tambah/save', 'Admin\Qr::save',  ['as' => 'admin.ac.add.save']);

                // Download ulang QR (PNG)
                $routes->get('(:num)/qr/download', 'Admin\AcUnits::downloadQr/$1', ['as' => 'admin.ac.qr.download']);
                
                 // **EXPORT**
                $routes->get('export', 'Admin\AcUnits::export', ['as' => 'admin.ac.export']);
            });
        });

        // Render PNG QR langsung untuk <img src="...">
        $routes->get('admin/qr/png/(:segment)', 'Admin\QrRender::show/$1', ['as' => 'admin.qr.show']);

        // QR (lama/opsional)
        $routes->get ('admin/qr',      'Admin\Qr::index', ['as' => 'admin.qr']);
        $routes->post('admin/qr/save', 'Admin\Qr::save',  ['as' => 'admin.qr.save']);
        if (ENVIRONMENT !== 'production') {
            $routes->get('admin/qr/diag', 'Admin\Qr::diag', ['as' => 'admin.qr.diag']);
        }

        // Pegawai (CRUD)
        $routes->group('admin', static function ($routes) {
            $routes->get   ('pegawai/export',         'Admin\Employees::export', ['as' => 'admin.emp.export']);
            $routes->get   ('pegawai',                'Admin\Employees::index');
            $routes->get   ('pegawai/search',         'Admin\Employees::search');
            $routes->get   ('pegawai/(:num)',         'Admin\Employees::show/$1');
            $routes->post  ('pegawai',                'Admin\Employees::store');
            $routes->post  ('pegawai/(:num)',         'Admin\Employees::update/$1');
            $routes->put   ('pegawai/(:num)',         'Admin\Employees::update/$1');
            $routes->delete('pegawai/(:num)',         'Admin\Employees::delete/$1');
            $routes->post  ('pegawai/(:num)/restore', 'Admin\Employees::restore/$1');
        });

        // Departemen (Bidang)
        $routes->group('admin', static function($r){
            
            $r->get ('bidang',              'Admin\Bidangs::index');
            $r->get ('bidang/search',       'Admin\Bidangs::search');
            $r->get ('bidang/(:num)',       'Admin\Bidangs::show/$1');      // JSON untuk Edit
            $r->post('bidang',              'Admin\Bidangs::store');        // Tambah
            $r->post('bidang/(:num)/save',  'Admin\Bidangs::update/$1');    // Update via POST (tanpa spoof)
            $r->post('bidang/(:num)/delete','Admin\Bidangs::delete/$1');    // Delete via POST
            
        });

        // =========================
        // ADMIN: Master Data Kendaraan
        // =========================
        $routes->group('admin/master', static function ($r) {
            $r->get   ('vehicles',             'Admin\Vehicles::index');
            $r->get   ('vehicles/search',      'Admin\Vehicles::search');
            $r->get   ('vehicles/(:num)',      'Admin\Vehicles::show/$1');
            $r->post  ('vehicles',             'Admin\Vehicles::store');
            $r->post  ('vehicles/(:num)',      'Admin\Vehicles::update/$1');      
            $r->post  ('vehicles/(:num)/delete','Admin\Vehicles::delete/$1');      
            $r->post  ('vehicles/(:num)/restore','Admin\Vehicles::restore/$1');

            // Fuel Prices
            $r->get ('fuel-prices',         'Admin\FuelPrices::index',  ['as'=>'admin.fuel.index']);
            $r->get ('fuel-prices/search',  'Admin\FuelPrices::search', ['as'=>'admin.fuel.search']);
            $r->post('fuel-prices',         'Admin\FuelPrices::store',  ['as'=>'admin.fuel.store']);
            $r->post('fuel-prices/(:num)',  'Admin\FuelPrices::update/$1', ['as'=>'admin.fuel.update']);
            $r->delete('fuel-prices/(:num)','Admin\FuelPrices::delete/$1', ['as'=>'admin.fuel.delete']);

            // Budgets
            $r->get ('budgets',             'Admin\Budgets::index',  ['as'=>'admin.budgets.index']);
            $r->get ('budgets/search',      'Admin\Budgets::search', ['as'=>'admin.budgets.search']);
            $r->post('budgets',             'Admin\Budgets::store',  ['as'=>'admin.budgets.store']);
            $r->post('budgets/(:num)',      'Admin\Budgets::update/$1', ['as'=>'admin.budgets.update']);
            $r->delete('budgets/(:num)',    'Admin\Budgets::delete/$1', ['as'=>'admin.budgets.delete']);
        });


        // =========================
        // ALIAS REST untuk AC Units (fix 404 DELETE /admin/ac-units/{id})
        // =========================
        $routes->get   ('admin/ac-units',        'Admin\AcUnits::index');               // list
        $routes->get   ('admin/ac-units/(:num)', 'Admin\AcUnits::show/$1');             // detail
        $routes->put   ('admin/ac-units/(:num)', 'Admin\AcUnits::update/$1');           // update (PUT)
        $routes->delete('admin/ac-units/(:num)', 'Admin\AcUnits::delete/$1', ['as' => 'admin.acunits.delete']);

    });

    /* ---------- USER (mobile-first) ---------- */
    $routes->group('user', ['filter' => 'role:user'], static function ($routes) {
        $routes->get('/',           'User\Home::index', ['as' => 'user.home']);
        $routes->get('home/stats',  'User\Home::stats', ['as' => 'user.home.stats']);

        // Placeholder rute user-side (nanti diisi)
        $routes->get ('kendaraan/riwayat', 'User\Kendaraan::riwayat'); // TODO: buat controller-nya
        $routes->get ('kendaraan/ajukan',  'User\Kendaraan::form');    // TODO
        $routes->post('kendaraan/ajukan',  'User\Kendaraan::store');   // TODO
        $routes->get ('kendaraan/bbm',     'User\Kendaraan::bbmForm'); // TODO
        $routes->post('kendaraan/bbm',     'User\Kendaraan::bbmStore');// TODO

        $routes->get ('ac/lapor',   'User\Ac::form');     // TODO
        $routes->post('ac/lapor',   'User\Ac::store');    // TODO
        $routes->get ('ac/status',  'User\Ac::status');   // TODO
    });


});

/* -----------------------------
 * Environment-based routes
 * ----------------------------- */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
