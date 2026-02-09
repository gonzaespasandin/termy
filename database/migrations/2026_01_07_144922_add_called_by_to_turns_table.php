<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->foreignId('called_by')->nullable()->after('attended_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('turns', function (Blueprint $table) {
            $table->dropForeign(['called_by']);
            $table->dropColumn('called_by');
        });
    }
};
