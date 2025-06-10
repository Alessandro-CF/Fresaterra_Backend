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
        Schema::create('productos', function (Blueprint $table) {
            $table->id('id_producto');
            $table->string('nombre');
            $table->text('descripcion');
            $table->decimal('precio', 10, 2);
            $table->string('url_imagen');
            $table->string('estado');
            $table->string('peso');
            $table->unsignedBigInteger('categorias_id_categoria');  // <-- campo para categoría
            $table->timestamp('fecha_creacion')->useCurrent();
            $table->timestamps();
        
            // Foreign key para asegurar integridad referencial
            $table->foreign('categorias_id_categoria')->references('id_categoria')->on('categorias')->onDelete('cascade');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
