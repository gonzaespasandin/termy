<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('display_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('video_enabled')->default(true);
            $table->string('video_path')->nullable();
            $table->unsignedBigInteger('channel_id')->nullable(); // reservado para canales futuros
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('display_settings');
    }
};
