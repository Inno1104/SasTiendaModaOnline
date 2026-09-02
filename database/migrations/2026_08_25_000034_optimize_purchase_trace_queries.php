<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_compras', function (Blueprint $table) {
            $table->index(['tienda_id', 'compra_id', 'id'], 'detalle_compra_tienda_compra');
        });

        Schema::table('stock_movimientos', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'referencia_type', 'referencia_id', 'created_at', 'id'],
                'stock_mov_tienda_referencia_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('detalle_compras', fn (Blueprint $table) => $table->dropIndex('detalle_compra_tienda_compra'));
        Schema::table('stock_movimientos', fn (Blueprint $table) => $table->dropIndex('stock_mov_tienda_referencia_fecha'));
    }
};
