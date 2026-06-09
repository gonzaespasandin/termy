<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->renameColumn('ask_customer_name', 'ask_customer_dni');
        });
    }

    public function down(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->renameColumn('ask_customer_dni', 'ask_customer_name');
        });
    }
};
