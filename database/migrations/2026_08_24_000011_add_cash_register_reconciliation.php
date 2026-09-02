<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->decimal('efectivo_esperado', 12, 2)->nullable()->after('monto_final');
            $table->decimal('efectivo_contado', 12, 2)->nullable()->after('efectivo_esperado');
            $table->decimal('diferencia', 12, 2)->nullable()->after('efectivo_contado');
            $table->string('estado_conciliacion', 20)->nullable()->after('diferencia');
            $table->string('observaciones_cierre', 500)->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->dropColumn([
                'efectivo_esperado',
                'efectivo_contado',
                'diferencia',
                'estado_conciliacion',
                'observaciones_cierre',
            ]);
        });
    }
};
