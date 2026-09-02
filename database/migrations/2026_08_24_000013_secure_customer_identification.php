<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_codigo_unique');
            $table->dropUnique('clientes_dni_unique');
            $table->unique(['tienda_id', 'codigo'], 'clientes_tienda_codigo_unique');
            $table->unique(['tienda_id', 'dni'], 'clientes_tienda_dni_unique');
            $table->unique(['tienda_id', 'email'], 'clientes_tienda_email_unique');
        });

        Schema::create('cliente_contadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->unique()->constrained('tiendas')->cascadeOnDelete();
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_contadores');

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_tienda_codigo_unique');
            $table->dropUnique('clientes_tienda_dni_unique');
            $table->dropUnique('clientes_tienda_email_unique');
            $table->unique('codigo');
            $table->unique('dni');
        });
    }
};
