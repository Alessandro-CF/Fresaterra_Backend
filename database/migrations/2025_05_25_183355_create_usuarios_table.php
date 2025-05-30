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
        Schema::create('usuarios', function (Blueprint $table) {
        $table->id('id_usuario');
    $table->string('nombre', 100);
    $table->string('apellidos', 100);
    $table->string('email', 100)->unique();
    $table->string('password', 255);
    $table->string('telefono', 12);
    $table->timestamp('fecha_creacion');
    $table->unsignedBigInteger('rols_id_rol');

    $table->foreign('rols_id_rol')->references('id_rol')->on('rols');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
    }
};
