<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->decimal('total_ingresos', 12, 2)->default(0)->after('total_ventas');
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->index();
            $table->foreignId('caja_id')->constrained('caja')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('tipo', ['ingreso', 'egreso']);
            $table->string('concepto', 150);
            $table->decimal('monto', 12, 2);
            $table->string('observaciones', 500)->nullable();
            $table->timestamps();

            $table->index(['tienda_id', 'caja_id', 'created_at'], 'movimientos_caja_turno_fecha');
            $table->index(['tienda_id', 'tipo', 'created_at'], 'movimientos_caja_tipo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
        Schema::table('caja', function (Blueprint $table) {
            $table->dropColumn('total_ingresos');
        });
    }
};
