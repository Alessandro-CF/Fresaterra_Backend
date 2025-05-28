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
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id('id_direccion');
            $table->string('calle');
            $table->string('numero');
            $table->string('distrito');
            $table->string('ciudad');
            $table->string('referencia')->nullable();
            $table->unsignedBigInteger('usuarios_id_usuario');
            $table->unsignedBigInteger('envios_id_envio');
            $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('users');
            $table->foreign('envios_id_envio')->references('id_envio')->on('envios');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
