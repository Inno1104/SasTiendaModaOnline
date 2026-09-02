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
            ->select('tienda_id', 'producto_id', 'talla', 'color')
            ->groupBy('tienda_id', 'producto_id', 'talla', 'color')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Existen combinaciones duplicadas de talla y color. Corríjalas antes de ejecutar esta migración.'
            );
        }

        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->unsignedInteger('lock_version')->default(0)->after('stock_minimo');
            $table->unique(
                ['tienda_id', 'producto_id', 'talla', 'color'],
                'variantes_producto_talla_color_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropUnique('variantes_producto_talla_color_unique');
            $table->dropColumn('lock_version');
        });
    }
};
