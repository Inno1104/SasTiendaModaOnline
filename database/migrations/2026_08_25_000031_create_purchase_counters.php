<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_contadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
            $table->unique(['tienda_id', 'anio'], 'compra_contador_tienda_anio_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_contadores');
    }
};
