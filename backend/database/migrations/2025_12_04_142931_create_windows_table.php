<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('windows', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "Ventanilla 1", "Mostrador 2"
            $table->integer('number')->unique();
            $table->json('services')->nullable(); // Array de service_ids que atiende
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_turn_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('windows');
    }
};
