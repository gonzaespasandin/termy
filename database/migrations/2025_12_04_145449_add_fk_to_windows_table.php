<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('windows', function (Blueprint $table) {
            $table->foreign('current_turn_id')->references('id')->on('turns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('windows', function (Blueprint $table) {
            $table->dropForeign(['current_turn_id']);
        });
    }
};
