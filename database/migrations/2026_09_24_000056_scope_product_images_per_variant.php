<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->index('producto_id', 'producto_imagenes_producto_idx');
        });
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->dropUnique('producto_imagenes_orden_unique');
            $table->unique(['producto_id', 'talla', 'color', 'orden'], 'producto_imagenes_variante_orden_unique');
        });
    }
    public function down(): void
    {
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->dropUnique('producto_imagenes_variante_orden_unique');
            $table->unique(['producto_id', 'orden'], 'producto_imagenes_orden_unique');
        });
        Schema::table('producto_imagenes', function (Blueprint $table) {
            $table->dropIndex('producto_imagenes_producto_idx');
        });
    }
};
