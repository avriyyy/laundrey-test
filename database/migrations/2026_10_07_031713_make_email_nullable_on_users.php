<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE users_new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR NOT NULL, email VARCHAR NULL UNIQUE, email_verified_at DATETIME NULL, password VARCHAR NOT NULL, remember_token VARCHAR(100) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, role VARCHAR NOT NULL DEFAULT \'pelanggan\', phone VARCHAR NULL, tenant_id INTEGER NULL)');
            DB::statement('INSERT INTO users_new (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id) SELECT id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id FROM users');
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::statement('CREATE TABLE users_new (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR NOT NULL, email VARCHAR NOT NULL UNIQUE, email_verified_at DATETIME NULL, password VARCHAR NOT NULL, remember_token VARCHAR(100) NULL, created_at DATETIME NULL, updated_at DATETIME NULL, role VARCHAR NOT NULL DEFAULT \'pelanggan\', phone VARCHAR NULL, tenant_id INTEGER NULL)');
            DB::statement("INSERT INTO users_new (id, name, email, email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id) SELECT id, name, COALESCE(email, 'restored-' || id || '@laundrey.local'), email_verified_at, password, remember_token, created_at, updated_at, role, phone, tenant_id FROM users");
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('ALTER TABLE users MODIFY email VARCHAR(100) NOT NULL');
        }
    }
};
