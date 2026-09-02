<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'stock', 'id'],
                'variantes_stock_orden_estable'
            );
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'activo', 'categoria_id', 'id'],
                'productos_stock_estado_categoria'
            );
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', fn (Blueprint $table) => $table->dropIndex('variantes_stock_orden_estable'));
        Schema::table('productos', fn (Blueprint $table) => $table->dropIndex('productos_stock_estado_categoria'));
    }
};
