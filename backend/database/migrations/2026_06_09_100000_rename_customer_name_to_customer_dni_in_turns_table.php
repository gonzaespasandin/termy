<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->renameColumn('customer_name', 'customer_dni');
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->renameColumn('customer_dni', 'customer_name');
        });
    }
};
