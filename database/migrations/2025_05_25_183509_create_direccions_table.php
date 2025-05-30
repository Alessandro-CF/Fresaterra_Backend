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
        Schema::create('direccions', function (Blueprint $table) {
         $table->id('id_direccion');
    $table->string('calle', 120);
    $table->string('numero', 10);
    $table->string('distrito', 20);
    $table->string('ciudad', 45);
    $table->string('referencia', 100)->nullable();
    $table->unsignedBigInteger('usuarios_id_usuario');
    $table->unsignedBigInteger('envios_id_envio');

    $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('usuarios');
    $table->foreign('envios_id_envio')->references('id_envio')->on('envios');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direccions');
    }
};
