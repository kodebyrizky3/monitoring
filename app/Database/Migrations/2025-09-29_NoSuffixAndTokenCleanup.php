<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NoSuffixAndTokenCleanup extends Migration
{
    public function up()
    {
        // === 1) HAPUS UNIQUE di nomor_unik (kalau ada) ===
        $idxs = $this->db->query("
            SELECT DISTINCT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ac_units'
              AND COLUMN_NAME = 'nomor_unik'
              AND NON_UNIQUE = 0
        ")->getResultArray();
        foreach ($idxs as $r) {
            $this->db->query("DROP INDEX `{$r['INDEX_NAME']}` ON `ac_units`");
        }

        // === 2) Hapus kolom turunan / index lama (jika sebelumnya pakai nomor_unik_lc) ===
        $hasLc = $this->db->query("
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='ac_units' AND COLUMN_NAME='nomor_unik_lc' LIMIT 1
        ")->getFirstRow();
        if ($hasLc) {
            $idxsLc = $this->db->query("
                SELECT DISTINCT INDEX_NAME
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'ac_units'
                  AND COLUMN_NAME = 'nomor_unik_lc'
                  AND NON_UNIQUE = 0
            ")->getResultArray();
            foreach ($idxsLc as $r) {
                $this->db->query("DROP INDEX `{$r['INDEX_NAME']}` ON `ac_units`");
            }
            $this->db->query("ALTER TABLE `ac_units` DROP COLUMN `nomor_unik_lc`");
        }

        // === 3) Pastikan kode_qr NOT NULL & UNIQUE (token resmi) ===
        // kolom bisa masih NULL di data lama → set dulu NOT NULL
        $this->db->query("
            ALTER TABLE `ac_units`
            MODIFY `kode_qr` VARCHAR(191) NOT NULL
        ");

        // buat unique index jika belum ada
        $hasQrUnique = $this->db->query("
            SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='ac_units'
              AND COLUMN_NAME='kode_qr' AND NON_UNIQUE=0 LIMIT 1
        ")->getFirstRow();
        if (!$hasQrUnique) {
            $this->db->query("CREATE UNIQUE INDEX `ac_units_kode_qr_unique` ON `ac_units`(`kode_qr`)");
        }

        // === 4) (Opsional) Hapus kolom legacy 'token' kalau masih ada ===
        $hasLegacyToken = $this->db->query("
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='ac_units' AND COLUMN_NAME='token' LIMIT 1
        ")->getFirstRow();
        if ($hasLegacyToken) {
            $this->db->query("ALTER TABLE `ac_units` DROP COLUMN `token`");
        }
    }

    public function down()
    {
        // Balikkan: hapus unique pada kode_qr
        $idxsQr = $this->db->query("
            SELECT DISTINCT INDEX_NAME
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'ac_units'
              AND COLUMN_NAME = 'kode_qr'
              AND NON_UNIQUE = 0
        ")->getResultArray();
        foreach ($idxsQr as $r) {
            $this->db->query("DROP INDEX `{$r['INDEX_NAME']}` ON `ac_units`");
        }

        // Kembalikan unique pada nomor_unik (warning: akan gagal jika sudah ada duplikat)
        $this->db->query("CREATE UNIQUE INDEX `ac_units_nomor_unik_unique` ON `ac_units`(`nomor_unik`)");

        // Kembalikan kolom token (nullable)
        $this->db->query("ALTER TABLE `ac_units` ADD COLUMN `token` VARCHAR(191) NULL");
    }
}
