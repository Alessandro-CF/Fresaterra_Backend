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
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id('id_inventario');
            $table->integer('cantidad_disponible');
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->timestamp('ultima_actualizacion')->useCurrent()->useCurrentOnUpdate();
            
            $table->string('estado');
            $table->unsignedBigInteger('productos_id_producto');
            $table->foreign('productos_id_producto')->references('id_producto')->on('productos');
         

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
