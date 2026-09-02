<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->index(
                ['tienda_id', 'activo', 'nombre_normalizado', 'id'],
                'categorias_tienda_estado_nombre'
            );
            $table->index(
                ['tienda_id', 'genero', 'nombre_normalizado', 'id'],
                'categorias_tienda_genero_nombre'
            );
        });

        Schema::table('subcategorias', function (Blueprint $table) {
            $table->index(['tienda_id', 'categoria_id', 'activo'], 'subcategorias_categoria_estado');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropIndex('categorias_tienda_estado_nombre');
            $table->dropIndex('categorias_tienda_genero_nombre');
        });

        Schema::table('subcategorias', function (Blueprint $table) {
            $table->dropIndex('subcategorias_categoria_estado');
        });
    }
};
