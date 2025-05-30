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
        Schema::create('reportes', function (Blueprint $table) {
          $table->id('id_reporte');
    $table->string('tipo', 45);
    $table->string('archivo_url', 255);
    $table->timestamp('fecha_creacion');
    $table->unsignedBigInteger('usuarios_id_usuario');

    $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('usuarios');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
