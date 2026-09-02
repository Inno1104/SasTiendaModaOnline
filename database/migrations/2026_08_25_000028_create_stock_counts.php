<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_conteos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes')->restrictOnDelete();
            $table->foreignId('contado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('stock_sistema');
            $table->unsignedInteger('stock_fisico');
            $table->integer('diferencia');
            $table->unsignedInteger('version_stock');
            $table->string('estado', 20)->default('pendiente');
            $table->string('motivo', 255)->nullable();
            $table->timestamp('conciliado_at')->nullable();
            $table->timestamps();

            $table->index(['tienda_id', 'variante_id', 'created_at'], 'conteos_tienda_variante_fecha');
            $table->index(['tienda_id', 'estado', 'created_at'], 'conteos_tienda_estado_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_conteos');
    }
};
