<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->uuid('operacion_id')->nullable()->after('tienda_id');
            $table->char('operacion_hash', 64)->nullable()->after('operacion_id');
            $table->unique(['tienda_id', 'operacion_id'], 'ventas_tienda_operacion_unique');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->uuid('operacion_id')->nullable()->after('tienda_id');
            $table->char('operacion_hash', 64)->nullable()->after('operacion_id');
            $table->unique(['tienda_id', 'operacion_id'], 'compras_tienda_operacion_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_tienda_operacion_unique');
            $table->dropColumn(['operacion_id', 'operacion_hash']);
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropUnique('compras_tienda_operacion_unique');
            $table->dropColumn(['operacion_id', 'operacion_hash']);
        });
    }
};
