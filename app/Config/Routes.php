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

    // kompat lama: /teknisi/perbaikan?t=TOKEN -> redirect ke /ac/{TOKEN}/perbaikan
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

        // Alias lama → arahkan ke halaman kendala baru
        $routes->get('data_kendala', 'Admin\Kendala::index', ['as' => 'admin.data_kendala']);

        // =========================
        // ADMIN: Data Alat → AC
        // =========================
        $routes->group('admin/data-alat', static function ($routes) {
            $routes->group('ac', static function ($routes) {
                // List + Search (JSON)
                $routes->get ('/',      'Admin\AcUnits::index',  ['as' => 'admin.ac.index']);
                $routes->get ('search', 'Admin\AcUnits::search', ['as' => 'admin.ac.search']);

                // Show / Edit / Update / Delete
                $routes->get ('(:num)',        'Admin\AcUnits::show/$1',   ['as' => 'admin.ac.show']);
                $routes->get ('(:num)/edit',   'Admin\AcUnits::edit/$1',   ['as' => 'admin.ac.edit']);
                $routes->post('(:num)/save',   'Admin\AcUnits::update/$1', ['as' => 'admin.ac.update']);
                $routes->post('(:num)/delete', 'Admin\AcUnits::delete/$1', ['as' => 'admin.ac.delete']);

                // Bulk delete (perbaikan path: relatif terhadap grup)
                $routes->post('bulk-delete', 'Admin\AcUnits::bulkDelete', ['as' => 'admin.ac.bulk_delete']);

                // Tambah via QR Generator (single)
                $routes->get ('tambah',      'Admin\Qr::index', ['as' => 'admin.ac.add']);
                $routes->post('tambah/save', 'Admin\Qr::save',  ['as' => 'admin.ac.add.save']);

                // Download ulang QR (PNG)
                $routes->get('(:num)/qr/download', 'Admin\AcUnits::downloadQr/$1', ['as' => 'admin.ac.qr.download']);

                // Export
                $routes->get('export', 'Admin\AcUnits::export', ['as' => 'admin.ac.export']);
                
            });
        });

        // Render PNG QR langsung (opsional)
        $routes->get('admin/qr/png/(:segment)', 'Admin\QrRender::show/$1', ['as' => 'admin.qr.show']);

        // QR Generator (UI) + API single + API bulk
        $routes->get ('admin/qr',           'Admin\Qr::index',     ['as' => 'admin.qr']);
        $routes->post('admin/qr/save',      'Admin\Qr::save',      ['as' => 'admin.qr.save']);
        $routes->post('admin/qr/bulk-save', 'Admin\Qr::bulkSave',  ['as' => 'admin.qr.bulk_save']);

        // 🔐 Diaktifkan di production juga → dipakai AJAX refresh CSRF
        $routes->get('admin/qr/diag', 'Admin\Qr::diag', ['as' => 'admin.qr.diag']);

        // Pegawai (CRUD)
        $routes->get   ('pegawai',        'Admin\Employees::index',  ['as' => 'admin.emp.index']);
        $routes->get   ('pegawai/(:num)', 'Admin\Employees::show/$1');
        $routes->post  ('pegawai',        'Admin\Employees::store');
        $routes->put   ('pegawai/(:num)', 'Admin\Employees::update/$1');
        $routes->delete('pegawai/(:num)', 'Admin\Employees::delete/$1');
        $routes->get   ('pegawai/search', 'Admin\Employees::search');

        // Kendaraan Unit (CRUD + kompat lama)
        $routes->get   ('kendaraan',               'Admin\KendaraanUnit::index',     ['as' => 'kendaraan.index']);
        $routes->get   ('kendaraan/(:num)',        'Admin\KendaraanUnit::json/$1',   ['as' => 'kendaraan.show']);
        $routes->post  ('kendaraan',               'Admin\KendaraanUnit::store',     ['as' => 'kendaraan.store']);
        $routes->put   ('kendaraan/(:num)',        'Admin\KendaraanUnit::update/$1', ['as' => 'kendaraan.update']);
        $routes->post  ('kendaraan/(:num)',        'Admin\KendaraanUnit::update/$1'); // kompat lama (POST)
        $routes->delete('kendaraan/(:num)',        'Admin\KendaraanUnit::delete/$1', ['as' => 'kendaraan.destroy']);
        $routes->post  ('kendaraan/delete/(:num)', 'Admin\KendaraanUnit::delete/$1', ['as' => 'kendaraan.delete']);
        $routes->get   ('kendaraan/search',        'Admin\KendaraanUnit::search',    ['as' => 'kendaraan.search']);

        // =========================
        // ADMIN: Data Kendala (baru)
        // =========================
        $routes->group('admin/kendala', ['namespace' => 'App\Controllers\Admin'], static function ($routes) {
            // Page + API list/export
            $routes->get('/',      'Kendala::index',  ['as' => 'admin.kendala.index']);
            $routes->get('search', 'Kendala::search', ['as' => 'admin.kendala.search']);
            $routes->get('export', 'Kendala::export', ['as' => 'admin.kendala.export']);

            // DETAIL
            $routes->get('ticket/(:num)',  'Kendala::detailTicket/$1',  ['as' => 'admin.kendala.ticket.detail']);
            $routes->get('service/(:num)', 'Kendala::detailService/$1', ['as' => 'admin.kendala.service.detail']);

            // ACTIONS
            $routes->post('ticket/(:num)/approve',  'Kendala::approveTicket/$1',  ['as' => 'admin.kendala.ticket.approve']);
            $routes->post('ticket/(:num)/reject',   'Kendala::rejectTicket/$1',   ['as' => 'admin.kendala.ticket.reject']);
            $routes->post('service/(:num)/approve', 'Kendala::approveService/$1', ['as' => 'admin.kendala.service.approve']);
            $routes->post('service/(:num)/reject',  'Kendala::rejectService/$1',  ['as' => 'admin.kendala.service.reject']);
        });

        // =========================
        // REST alias untuk AC Units (fix method PUT/DELETE dari client)
        // =========================
        $routes->get   ('admin/ac-units',        'Admin\AcUnits::index');               // list
        $routes->get   ('admin/ac-units/(:num)', 'Admin\AcUnits::show/$1');             // detail
        $routes->put   ('admin/ac-units/(:num)', 'Admin\AcUnits::update/$1');           // update (PUT)
        $routes->delete('admin/ac-units/(:num)', 'Admin\AcUnits::delete/$1', ['as' => 'admin.acunits.delete']); // delete (DELETE)
        // Jika ada method store() di controller, aktifkan:
        // $routes->post('admin/ac-units', 'Admin\AcUnits::store'); // create
    });

    /* ---------- USER (mobile-first) ---------- */
    $routes->group('u', static function ($routes) {

        // Landing user → /u/kendaraan
        $routes->get('', static fn () => redirect()->to(site_url('u/kendaraan')));

        // Page (UI)
        $routes->get('kendaraan', 'User\Kendaraan\Page::index');

        // Perjalanan Dinas
        $routes->get ('kendaraan/perjalanan/search',        'User\Kendaraan\Trips::search');
        $routes->post('kendaraan/perjalanan',               'User\Kendaraan\Trips::start');
        $routes->put ('kendaraan/perjalanan/(:num)/finish', 'User\Kendaraan\Trips::finish/$1');
        $routes->post('kendaraan/perjalanan/(:num)/finish', 'User\Kendaraan\Trips::finish/$1'); // spoof POST

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

