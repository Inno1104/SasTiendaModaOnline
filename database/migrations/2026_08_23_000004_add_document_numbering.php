<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            $table->string('serie_ticket', 10)->default('T001')->after('serie_factura');
        });

        Schema::create('comprobante_contadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('serie', 10);
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->timestamps();
            $table->unique(['tienda_id', 'tipo', 'serie'], 'comprobante_contador_unico');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_numero_venta_unique');
            $table->index('numero_venta', 'ventas_numero_venta_index');
            $table->unique(
                ['tienda_id', 'tipo_comprobante', 'serie', 'correlativo'],
                'ventas_comprobante_unico_por_tienda'
            );
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_comprobante_unico_por_tienda');
            $table->dropIndex('ventas_numero_venta_index');
            $table->unique('numero_venta', 'ventas_numero_venta_unique');
        });
        Schema::dropIfExists('comprobante_contadores');
        Schema::table('tiendas', fn (Blueprint $table) => $table->dropColumn('serie_ticket'));
    }
};
