<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_settings', function (Blueprint $table) {
            $table->string('weather_city')->nullable()->after('channel_id');
            $table->decimal('weather_lat', 10, 6)->nullable()->after('weather_city');
            $table->decimal('weather_lon', 10, 6)->nullable()->after('weather_lat');
        });
    }

    public function down(): void
    {
        Schema::table('display_settings', function (Blueprint $table) {
            $table->dropColumn(['weather_city', 'weather_lat', 'weather_lon']);
        });
    }
};
