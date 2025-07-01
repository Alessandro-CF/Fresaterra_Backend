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
        Schema::create('comentarios', function (Blueprint $table) {
            $table->id('id_resena');
            $table->integer('calificacion')->nullable();
            $table->text('contenido')->nullable();
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->unsignedBigInteger('usuarios_id_usuario');
            $table->unsignedBigInteger('productos_id_producto'); // Add this line
            $table->foreign('usuarios_id_usuario')->references('id_usuario')->on('users');
            $table->foreign('productos_id_producto')->references('id_producto')->on('productos'); // Add this line
            $table->unique(['usuarios_id_usuario', 'productos_id_producto']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comentarios');
    }
};