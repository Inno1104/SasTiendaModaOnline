<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('producto_variantes')
            ->select('tienda_id', 'codigo_barra')
            ->whereNotNull('codigo_barra')
            ->where('codigo_barra', '<>', '')
            ->groupBy('tienda_id', 'codigo_barra')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Existen códigos de barras duplicados dentro de una tienda. Corríjalos antes de ejecutar esta migración.'
            );
        }

        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropIndex('variantes_codigo_barra_tienda');
            $table->unique(['tienda_id', 'codigo_barra'], 'variantes_tienda_barra_unique');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropUnique('variantes_tienda_barra_unique');
            $table->index(['tienda_id', 'codigo_barra'], 'variantes_codigo_barra_tienda');
        });
    }
};
