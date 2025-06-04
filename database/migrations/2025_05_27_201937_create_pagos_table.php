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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id('id_pago');
            $table->timestamp('fecha_pago');
            $table->decimal('monto_pago', 10, 2);
            $table->string('estado_pago');
            $table->string('referencia_pago');
            $table->unsignedBigInteger('pedidos_id_pedido');
            $table->unsignedBigInteger('metodos_pago_id_metodo_pago');
            $table->foreign('pedidos_id_pedido')->references('id_pedido')->on('pedidos');
            $table->foreign('metodos_pago_id_metodo_pago')->references('id_metodo_pago')->on('metodos_pago');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};