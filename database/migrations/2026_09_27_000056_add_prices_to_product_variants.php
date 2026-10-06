<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->decimal('precio_compra', 10, 2)->nullable()->after('codigo_barra');
            $table->decimal('precio_venta', 10, 2)->nullable()->after('precio_compra');
            $table->decimal('precio_oferta', 10, 2)->nullable()->after('precio_venta');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', fn (Blueprint $table) => $table->dropColumn(['precio_compra', 'precio_venta', 'precio_oferta']));
    }
};
