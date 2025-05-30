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
        Schema::create('notificacions', function (Blueprint $table) {
        $table->id('id_notificacion');
    $table->string('estado', 45);
    $table->timestamp('fecha_creacion');
    $table->unsignedBigInteger('usuarios_id_usuario');
    $table->unsignedBigInteger('mensajes_id_mensaje');

    $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('usuarios');
    $table->foreign('mensajes_id_mensaje')->references('id_mensaje')->on('mensajes');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificacions');
    }
};
