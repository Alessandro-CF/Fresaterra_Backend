<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agregar campos snapshot optimizados para preservar solo datos históricos realmente utilizados
     */
    public function up(): void
    {
        // 1. Snapshot de productos en pedido_items (solo campos que realmente se usan)
        Schema::table('pedido_items', function (Blueprint $table) {
            // Snapshot del producto en el momento del pedido
            $table->string('producto_nombre_snapshot')->nullable()->after('productos_id_producto');
            $table->text('producto_descripcion_snapshot')->nullable()->after('producto_nombre_snapshot');
            $table->string('producto_imagen_snapshot')->nullable()->after('producto_descripcion_snapshot');
            $table->string('producto_peso_snapshot')->nullable()->after('producto_imagen_snapshot');
            $table->string('categoria_nombre_snapshot')->nullable()->after('producto_peso_snapshot');
            
            // El precio ya está en la tabla como 'precio' (no se duplica)
        });

        // 2. Snapshot de dirección en envios (campos optimizados según uso real)
        Schema::table('envios', function (Blueprint $table) {
            // Snapshot de la dirección - formato combinado como se usa en frontend
            $table->string('direccion_linea1_snapshot')->nullable()->after('direcciones_id_direccion')->comment('calle + numero combinados');
            $table->string('direccion_linea2_snapshot')->nullable()->after('direccion_linea1_snapshot')->comment('referencia');
            $table->string('direccion_ciudad_snapshot')->nullable()->after('direccion_linea2_snapshot');
            $table->string('direccion_estado_snapshot')->nullable()->after('direccion_ciudad_snapshot')->comment('distrito');
            
            // Snapshot del transportista (solo campos que existen y se usan)
            $table->string('transportista_nombre_snapshot')->nullable()->after('direccion_estado_snapshot');
            $table->string('transportista_telefono_snapshot')->nullable()->after('transportista_nombre_snapshot');
        });

        // 3. Snapshot de método de pago en pagos (solo campos que existen)
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('metodo_pago_nombre_snapshot')->nullable()->after('metodos_pago_id_metodo_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pedido_items', function (Blueprint $table) {
            $table->dropColumn([
                'producto_nombre_snapshot',
                'producto_descripcion_snapshot', 
                'producto_imagen_snapshot',
                'producto_peso_snapshot',
                'categoria_nombre_snapshot'
            ]);
        });

        Schema::table('envios', function (Blueprint $table) {
            $table->dropColumn([
                'direccion_linea1_snapshot',
                'direccion_linea2_snapshot',
                'direccion_ciudad_snapshot',
                'direccion_estado_snapshot',
                'transportista_nombre_snapshot',
                'transportista_telefono_snapshot'
            ]);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'metodo_pago_nombre_snapshot'
            ]);
        });
    }
};
