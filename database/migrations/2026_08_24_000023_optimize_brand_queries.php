<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'activo', 'nombre_normalizado', 'id'],
                'marcas_tienda_estado_nombre'
            );
            $table->index(
                ['tienda_id', 'created_at', 'id'],
                'marcas_tienda_fecha'
            );
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'marca_id', 'activo'],
                'productos_tienda_marca_estado'
            );
        });
    }

    public function down(): void
    {
        Schema::table('marcas', function (Blueprint $table) {
            $table->dropIndex('marcas_tienda_estado_nombre');
            $table->dropIndex('marcas_tienda_fecha');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_tienda_marca_estado');
        });
    }
};
