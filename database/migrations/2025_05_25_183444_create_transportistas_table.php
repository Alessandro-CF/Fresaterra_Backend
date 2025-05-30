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
        Schema::create('transportistas', function (Blueprint $table) {
           $table->id('id_transportista');
    $table->string('nombre', 100);
    $table->string('telefono', 12);
    $table->string('tipo_transporte', 45);
    $table->string('empresa', 100)->nullable();
    $table->string('placa_vehiculo', 9);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transportistas');
    }
};
