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
            $table->string('predeterminada')->default('si'); // 'si' o 'no'
            $table->unsignedBigInteger('usuarios_id_usuario');
            $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('users');
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
