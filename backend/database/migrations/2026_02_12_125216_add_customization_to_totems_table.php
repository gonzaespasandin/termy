<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('ask_customer_name');
            $table->enum('background_type', ['solid', 'gradient'])->default('gradient')->after('logo');
            $table->string('background_color_1')->default('#667eea')->after('background_type');
            $table->string('background_color_2')->nullable()->default('#764ba2')->after('background_color_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('totems', function (Blueprint $table) {
            $table->dropColumn(['logo', 'background_type', 'background_color_1', 'background_color_2']);
        });
    }
};
