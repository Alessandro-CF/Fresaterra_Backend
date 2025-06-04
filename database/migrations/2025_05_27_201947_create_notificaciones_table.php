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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id('id_notificacion');
            $table->string('estado');
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->unsignedBigInteger('usuarios_id_usuario');
            $table->unsignedBigInteger('mensajes_id_mensaje');
            $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('users');
            $table->foreign('mensajes_id_mensaje')->references('id_mensaje')->on('mensajes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};