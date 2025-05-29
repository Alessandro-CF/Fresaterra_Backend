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
        Schema::table('notificaciones', function (Blueprint $table) {
            // Añadir campos de tipo campanita
            $table->uuid('uuid')->after('id_notificacion')->nullable();
            $table->string('type')->after('estado')->nullable();
            $table->timestamp('read_at')->after('fecha_creacion')->nullable();
            $table->json('data')->after('mensajes_id_mensaje')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'type', 'read_at', 'data']);
        });
    }
};
