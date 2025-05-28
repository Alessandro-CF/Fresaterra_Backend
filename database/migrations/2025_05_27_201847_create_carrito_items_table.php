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
        Schema::create('carrito_items', function (Blueprint $table) {
            $table->id('id_carrito_items');
            $table->integer('cantidad')->nullable();
            $table->unsignedBigInteger('carritos_id_carrito');
            $table->unsignedBigInteger('productos_id_producto');
            $table->foreign('carritos_id_carrito')->references('id_carrito')->on('carritos');
            $table->foreign('productos_id_producto')->references('id_producto')->on('productos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carrito_items');
    }
};
