<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'created_at', 'id'],
                'productos_catalogo_tienda_fecha'
            );
            $table->index(
                ['tienda_id', 'categoria_id', 'created_at', 'id'],
                'productos_catalogo_categoria_fecha'
            );
            $table->index(
                ['tienda_id', 'genero', 'tipo', 'created_at', 'id'],
                'productos_catalogo_genero_tipo_fecha'
            );
            $table->index(
                ['tienda_id', 'tipo', 'created_at', 'id'],
                'productos_catalogo_tipo_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_catalogo_tienda_fecha');
            $table->dropIndex('productos_catalogo_categoria_fecha');
            $table->dropIndex('productos_catalogo_genero_tipo_fecha');
            $table->dropIndex('productos_catalogo_tipo_fecha');
        });
    }
};
