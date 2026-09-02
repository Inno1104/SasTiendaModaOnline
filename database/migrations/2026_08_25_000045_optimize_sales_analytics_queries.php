<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['tienda_id', 'estado', 'fecha', 'id'], 'ventas_analitica_estado_fecha_id');
            $table->index(['tienda_id', 'caja_id', 'estado', 'id'], 'ventas_caja_estado_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_analitica_estado_fecha_id');
            $table->dropIndex('ventas_caja_estado_id');
        });
    }
};
