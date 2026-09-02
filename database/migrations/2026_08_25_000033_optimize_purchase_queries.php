<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->index(['tienda_id', 'estado', 'fecha', 'id'], 'compras_tienda_estado_fecha');
            $table->index(['tienda_id', 'fecha', 'id'], 'compras_tienda_fecha_orden');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('compras_tienda_estado_fecha');
            $table->dropIndex('compras_tienda_fecha_orden');
        });
    }
};
