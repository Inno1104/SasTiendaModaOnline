<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['tienda_id', 'created_at'], 'ventas_historial_tienda_fecha');
            $table->index(['tienda_id', 'user_id', 'created_at'], 'ventas_historial_vendedor_fecha');
            $table->index(['tienda_id', 'caja_id', 'created_at'], 'ventas_historial_caja_fecha');
        });

        Schema::table('pagos_venta', function (Blueprint $table) {
            $table->index(['venta_id', 'metodo'], 'pagos_historial_venta_metodo');
            $table->index(['venta_id', 'referencia'], 'pagos_historial_venta_referencia');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['tienda_id', 'activo', 'name'], 'usuarios_historial_tienda_activo_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_historial_tienda_fecha');
            $table->dropIndex('ventas_historial_vendedor_fecha');
            $table->dropIndex('ventas_historial_caja_fecha');
        });

        Schema::table('pagos_venta', function (Blueprint $table) {
            $table->dropIndex('pagos_historial_venta_metodo');
            $table->dropIndex('pagos_historial_venta_referencia');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('usuarios_historial_tienda_activo_nombre');
        });
    }
};
