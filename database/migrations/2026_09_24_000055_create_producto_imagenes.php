<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('producto_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('talla', 50)->nullable();
            $table->string('color', 80)->nullable();
            $table->unsignedTinyInteger('orden');
            $table->boolean('principal')->default(false);
            $table->timestamps();
            $table->unique(['producto_id', 'orden'], 'producto_imagenes_orden_unique');
            $table->index(['tienda_id', 'producto_id', 'color', 'talla'], 'producto_imagenes_variante_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');
    }
};
