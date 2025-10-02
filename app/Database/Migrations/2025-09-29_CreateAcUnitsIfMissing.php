<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAcUnitsIfMissing extends Migration
{
    public function up()
    {
        // Buat tabel minimal jika belum ada
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `ac_units` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kode_qr` VARCHAR(191) NOT NULL,
                `nomor_unik` VARCHAR(191) NOT NULL,
                `tipe_model` VARCHAR(191) NOT NULL DEFAULT '-',
                `kapasitas_btu` INT NOT NULL DEFAULT 12000,
                `lokasi` VARCHAR(191) NOT NULL DEFAULT '-',
                `status_ac` ENUM('NORMAL','MENUNGGU_PERBAIKAN','DALAM_PERBAIKAN') NOT NULL DEFAULT 'NORMAL',
                `catatan` TEXT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `ac_units_kode_qr_unique` (`kode_qr`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down()
    {
        // Hati-hati: biasanya tidak kita drop tabel produksi
        // $this->db->query("DROP TABLE IF EXISTS `ac_units`");
    }
}
