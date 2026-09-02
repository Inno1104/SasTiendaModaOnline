<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->index(['tienda_id', 'fecha', 'estado'], 'caja_historial_fecha_estado');
            $table->index(['tienda_id', 'user_id', 'fecha'], 'caja_historial_responsable_fecha');
            $table->index(['tienda_id', 'estado_conciliacion', 'fecha'], 'caja_historial_conciliacion_fecha');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['tienda_id', 'caja_id', 'estado'], 'ventas_caja_estado');
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->index(['caja_id', 'tipo'], 'movimientos_caja_tipo');
        });
    }

    public function down(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->dropIndex('caja_historial_fecha_estado');
            $table->dropIndex('caja_historial_responsable_fecha');
            $table->dropIndex('caja_historial_conciliacion_fecha');
        });
        Schema::table('ventas', fn (Blueprint $table) => $table->dropIndex('ventas_caja_estado'));
        Schema::table('movimientos_caja', fn (Blueprint $table) => $table->dropIndex('movimientos_caja_tipo'));
    }
};
