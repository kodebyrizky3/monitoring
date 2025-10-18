<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBidangIdToEmployees extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('employees')) {
            // kalau tabel employees belum ada, kamu yang buat di migration lain
            return;
        }

        $fields = $db->getFieldNames('employees');

        // tambah kolom bidang_id jika belum ada
        if (!in_array('bidang_id', $fields, true)) {
            $db->query("ALTER TABLE employees ADD COLUMN bidang_id INT UNSIGNED NULL AFTER no_telp");
        }

        // tambah deleted_at untuk soft delete kalau belum ada
        if (!in_array('deleted_at', $fields, true)) {
            $db->query("ALTER TABLE employees ADD COLUMN deleted_at DATETIME NULL AFTER updated_at");
        }

        // pastikan ada index utk bidang_id
        $idx = $db->query("SHOW INDEX FROM employees WHERE Column_name='bidang_id'")->getResultArray();
        if (empty($idx)) {
            $db->query("ALTER TABLE employees ADD INDEX idx_employees_bidang_id (bidang_id)");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('employees')) {
            // aman: buang FK di migration berikut saat down
            try { $db->query("ALTER TABLE employees DROP INDEX idx_employees_bidang_id"); } catch (\Throwable $e) {}
            try { $db->query("ALTER TABLE employees DROP COLUMN bidang_id"); } catch (\Throwable $e) {}
            try { $db->query("ALTER TABLE employees DROP COLUMN deleted_at"); } catch (\Throwable $e) {}
        }
    }
}
