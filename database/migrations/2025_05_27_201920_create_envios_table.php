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
        Schema::create('envios', function (Blueprint $table) {
            $table->id('id_envio');
            $table->decimal('monto_envio', 8, 2);
            $table->string('estado');
            $table->timestamp('fecha_envio');
            $table->unsignedBigInteger('transportistas_id_transportista');
            $table->unsignedBigInteger('pedidos_id_pedido');
            $table->foreign('transportistas_id_transportista')->references('id_transportista')->on('transportistas');
            $table->foreign('pedidos_id_pedido')->references('id_pedido')->on('pedidos');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};