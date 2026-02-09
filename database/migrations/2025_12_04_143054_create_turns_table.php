<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->unique(); // "AC-001", "VT-015"
            $table->enum('status', [
                'waiting',      // En espera
                'called',       // Llamado
                'in_progress',  // En atención
                'completed',    // Completado
                'cancelled',    // Cancelado
                'no_show'       // No se presentó
            ])->default('waiting');
            $table->enum('priority', ['normal', 'priority'])->default('normal');
            
            // Datos opcionales del cliente
            $table->string('customer_name')->nullable();
            //$table->string('customer_document')->nullable();
            //$table->string('customer_phone')->nullable();
            //$table->string('customer_email')->nullable();
            
            // Seguimiento de tiempos
            $table->timestamp('created_at'); // Hora de emisión
            $table->timestamp('called_at')->nullable(); // Hora de llamado
            $table->timestamp('attended_at')->nullable(); // Hora de inicio atención
            $table->timestamp('completed_at')->nullable(); // Hora de finalización
            $table->timestamp('updated_at')->nullable();
            
            // Ventanilla que lo atendió
            $table->foreignId('window_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete(); // Usuario que atendió
            
            $table->text('notes')->nullable();
            
            // Índices para optimizar consultas
            $table->index(['service_id', 'status', 'created_at']);
            $table->index('ticket_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turns');
    }
};