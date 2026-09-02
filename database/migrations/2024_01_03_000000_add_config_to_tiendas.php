<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            if (!Schema::hasColumn('tiendas', 'igv')) {
                $table->decimal('igv', 5, 2)->default(18)->after('logo');
            }
            if (!Schema::hasColumn('tiendas', 'moneda')) {
                $table->string('moneda', 10)->default('USD')->after('igv');
            }
            if (!Schema::hasColumn('tiendas', 'simbolo_moneda')) {
                $table->string('simbolo_moneda', 5)->default('$')->after('moneda');
            }
            if (!Schema::hasColumn('tiendas', 'serie_boleta')) {
                $table->string('serie_boleta', 10)->default('B001')->after('simbolo_moneda');
            }
            if (!Schema::hasColumn('tiendas', 'serie_factura')) {
                $table->string('serie_factura', 10)->default('F001')->after('serie_boleta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tiendas', function (Blueprint $table) {
            foreach (['igv', 'moneda', 'simbolo_moneda', 'serie_boleta', 'serie_factura'] as $c) {
                if (Schema::hasColumn('tiendas', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
