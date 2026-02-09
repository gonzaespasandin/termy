<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar campo issued_at para la hora de emisión del ticket
        Schema::table('turns', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable()->after('customer_name');
        });

        // Copiar los valores actuales de created_at a issued_at
        DB::statement('UPDATE turns SET issued_at = created_at');

        // Hacer issued_at NOT NULL después de copiar los datos
        Schema::table('turns', function (Blueprint $table) {
            $table->timestamp('issued_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->dropColumn('issued_at');
        });
    }
};
