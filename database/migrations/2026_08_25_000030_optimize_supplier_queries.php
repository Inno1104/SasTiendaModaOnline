<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->index(['tienda_id', 'activo', 'nombre', 'id'], 'proveedores_tienda_estado_nombre');
            $table->index(['tienda_id', 'created_at', 'id'], 'proveedores_tienda_fecha');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index(['tienda_id', 'proveedor_id'], 'productos_tienda_proveedor');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->index(['tienda_id', 'proveedor_id'], 'compras_tienda_proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex('proveedores_tienda_estado_nombre');
            $table->dropIndex('proveedores_tienda_fecha');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_tienda_proveedor');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('compras_tienda_proveedor');
        });
    }
};
