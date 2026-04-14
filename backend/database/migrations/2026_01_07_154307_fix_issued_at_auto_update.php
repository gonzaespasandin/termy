<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Arreglar issued_at para que NO tenga ON UPDATE CURRENT_TIMESTAMP
        DB::statement('ALTER TABLE turns MODIFY COLUMN issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
        
        // También arreglar created_at por las dudas
        DB::statement('ALTER TABLE turns MODIFY COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // No es necesario revertir
    }
};
