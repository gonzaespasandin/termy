<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN is MySQL-only syntax. SQLite doesn't have ON UPDATE CURRENT_TIMESTAMP
        // so this fix is only needed on MySQL/MariaDB connections.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE turns MODIFY COLUMN issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
            DB::statement('ALTER TABLE turns MODIFY COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down(): void
    {
        // No es necesario revertir
    }
};
