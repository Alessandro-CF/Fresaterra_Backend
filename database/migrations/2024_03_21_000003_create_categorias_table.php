<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id('id_categoria');
            $table->string('nombre', 100)->notNullable();
            $table->text('descripcion')->notNullable();
            $table->timestamp('fecha_creacion')->notNullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
}; 