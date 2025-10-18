<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFkUsersEmployee extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('users') || ! $db->tableExists('employees')) return;

        $fields = $db->getFieldNames('users');
        if (!in_array('employee_id', $fields, true)) return;

        $schema = $db->query('SELECT DATABASE() AS db')->getRow('db');
        $fk = $db->query("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'employee_id'
              AND REFERENCED_TABLE_NAME = 'employees'
        ", [$schema])->getResultArray();

        if (empty($fk)) {
            $db->query("
                ALTER TABLE users
                ADD CONSTRAINT fk_users_employee
                FOREIGN KEY (employee_id) REFERENCES employees(id)
                ON UPDATE CASCADE ON DELETE SET NULL
            ");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try { $db->query("ALTER TABLE users DROP FOREIGN KEY fk_users_employee"); } catch (\Throwable $e) {}
    }
}
