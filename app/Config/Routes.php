<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ==========================
// Keamanan
// ==========================
$routes->setAutoRoute(false);

// ==========================
// AUTH (PUBLIC)
// ==========================
$routes->get('login',      'Admin\Auth::login', ['as' => 'auth.login']);
$routes->post('auth/do',   'Admin\Auth::do',    ['as' => 'auth.do']);
// fallback GET ke /auth/do -> /login
$routes->get('auth/do', static fn () => redirect()->to(site_url('login')));
$routes->get('logout',     'Admin\Auth::logout', ['as' => 'auth.logout']);

// ==========================
// TEKNISI via TOKEN (PUBLIC)
// ==========================
$routes->group('', static function ($routes) {
    $routes->get('ac/(:segment)',           'Teknisi\Page::detailByToken/$1',    ['as' => 'teknisi.ac.detail']);
    $routes->get('ac/(:segment)/perbaikan', 'Teknisi\Page::perbaikanByToken/$1', ['as' => 'teknisi.ac.repair']);

    // kompat lama
    $routes->get('teknisi/perbaikan', static function () {
        $t = service('request')->getGet('t');
        return $t
            ? redirect()->to(site_url('ac/' . rawurlencode($t) . '/perbaikan'))
            : redirect()->to(site_url('/'));
    });
});

// ==========================
// AREA TERPROTEKSI (WAJIB LOGIN)
// ==========================
$routes->group('', ['filter' => 'auth'], static function ($routes) {

    // ---------- ADMIN ----------
    $routes->group('', ['filter' => 'role:admin'], static function ($routes) {

        // Dashboard
        $routes->get('/',         'Admin\Dashboard::index', ['as' => 'admin.home']);
        $routes->get('dashboard', 'Admin\Dashboard::index', ['as' => 'admin.dashboard']);

        // Lain-lain
        $routes->get('data_kendala', 'Admin\Data_kendala::data_kendala', ['as' => 'admin.data_kendala']);
        $routes->get('admin/qr',     'Admin\Qr::index',                  ['as' => 'admin.qr']);

        // Chart & Notif
        $routes->get('dashboard/chart-data', 'Dashboard::chartData', ['as' => 'admin.chart_data']);
        $routes->get('notifications/latest', 'Notifications::latest', ['as' => 'admin.notif.latest']);
        $routes->get('notifications/stream', 'Notifications::stream', ['as' => 'admin.notif.stream']);

        // Pegawai (CRUD)
        $routes->get   ('pegawai',               'Admin\Employees::index', ['as' => 'admin.emp.index']);
        $routes->get   ('pegawai/(:num)',        'Admin\Employees::show/$1');
        $routes->post  ('pegawai',               'Admin\Employees::store');
        $routes->put   ('pegawai/(:num)',        'Admin\Employees::update/$1');
        $routes->delete('pegawai/(:num)',        'Admin\Employees::delete/$1');
        $routes->get   ('pegawai/search',        'Admin\Employees::search');

        // Kendaraan Unit (CRUD)
        $routes->get   ('kendaraan',               'Admin\KendaraanUnit::index',     ['as' => 'kendaraan.index']);
        $routes->get   ('kendaraan/(:num)',        'Admin\KendaraanUnit::json/$1',   ['as' => 'kendaraan.show']);
        $routes->post  ('kendaraan',               'Admin\KendaraanUnit::store',     ['as' => 'kendaraan.store']);
        $routes->put   ('kendaraan/(:num)',        'Admin\KendaraanUnit::update/$1', ['as' => 'kendaraan.update']);
        $routes->post  ('kendaraan/(:num)',        'Admin\KendaraanUnit::update/$1'); // kompat lama
        $routes->delete('kendaraan/(:num)',        'Admin\KendaraanUnit::delete/$1', ['as' => 'kendaraan.destroy']);
        $routes->post  ('kendaraan/delete/(:num)', 'Admin\KendaraanUnit::delete/$1', ['as' => 'kendaraan.delete']); // kompat lama
        $routes->get   ('kendaraan/search',        'Admin\KendaraanUnit::search',    ['as' => 'kendaraan.search']);

        // =========================
        // ADMIN: Data Kendala (gabungan vw_admin_kendala)
        // =========================
        $routes->group('admin/kendala', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
            // Page + API list/export
            $routes->get('/',        'Kendala::index');
            $routes->get('search',   'Kendala::search');
            $routes->get('export',   'Kendala::export');

            // Detail (GET)
            $routes->get('ticket/(:num)',  'Kendala::detailTicket/$1');
            $routes->get('service/(:num)', 'Kendala::detailService/$1');

            // Actions (POST)  <<< JANGAN ulangi prefix "admin/kendala" di sini
            $routes->post('ticket/(:num)/approve',  'Kendala::approveTicket/$1');
            $routes->post('ticket/(:num)/reject',   'Kendala::rejectTicket/$1');
            $routes->post('service/(:num)/approve', 'Kendala::approveService/$1');
            $routes->post('service/(:num)/reject',  'Kendala::rejectService/$1');
        });

    });

    // ---------- USER (mobile-first) ----------
    $routes->group('u', static function ($routes) {

        // Landing user → /u/kendaraan
        $routes->get('', static fn () => redirect()->to(site_url('u/kendaraan')));

        // Page (UI)
        $routes->get('kendaraan', 'User\Kendaraan\Page::index');

        // Perjalanan Dinas
        $routes->get ('kendaraan/perjalanan/search',        'User\Kendaraan\Trips::search');
        $routes->post('kendaraan/perjalanan',               'User\Kendaraan\Trips::start');
        $routes->put ('kendaraan/perjalanan/(:num)/finish', 'User\Kendaraan\Trips::finish/$1');
        $routes->post('kendaraan/perjalanan/(:num)/finish', 'User\Kendaraan\Trips::finish/$1'); // spoof

        // Laporan Kerusakan (Tickets)
        $routes->get ('kendaraan/kerusakan/search', 'User\Kendaraan\Tickets::search');
        $routes->post('kendaraan/kerusakan',        'User\Kendaraan\Tickets::store');

        // Perbaikan / Service (Services)
        $routes->get ('kendaraan/perbaikan/search', 'User\Kendaraan\Services::search');
        $routes->post('kendaraan/perbaikan',        'User\Kendaraan\Services::store');

        // OPTIONS (dropdown)
        $routes->get('options/kendaraan', 'User\Kendaraan\Options::kendaraan');
        $routes->get('options/pegawai',   'User\Kendaraan\Options::pegawai');
    });

});
