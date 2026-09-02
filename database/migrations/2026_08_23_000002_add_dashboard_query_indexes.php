<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->index(['tienda_id', 'estado', 'fecha'], 'dash_ventas_tienda_estado_fecha'));
        Schema::table('clientes', fn (Blueprint $table) => $table->index(['tienda_id', 'activo', 'created_at'], 'dash_clientes_tienda_activo_fecha'));
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['tienda_id', 'activo', 'created_at'], 'dash_productos_tienda_activo_fecha');
            $table->index(['tienda_id', 'categoria_id'], 'dash_productos_tienda_categoria');
        });
        Schema::table('producto_variantes', fn (Blueprint $table) => $table->index(['tienda_id', 'producto_id'], 'dash_variantes_tienda_producto'));
        Schema::table('detalle_ventas', fn (Blueprint $table) => $table->index(['tienda_id', 'venta_id', 'producto_id'], 'dash_detalles_tienda_venta_producto'));
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropIndex('dash_ventas_tienda_estado_fecha'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropIndex('dash_clientes_tienda_activo_fecha'));
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('dash_productos_tienda_activo_fecha');
            $table->dropIndex('dash_productos_tienda_categoria');
        });
        Schema::table('producto_variantes', fn (Blueprint $table) => $table->dropIndex('dash_variantes_tienda_producto'));
        Schema::table('detalle_ventas', fn (Blueprint $table) => $table->dropIndex('dash_detalles_tienda_venta_producto'));
    }
};
