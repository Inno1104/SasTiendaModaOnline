<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('caja_id')->nullable()->after('user_id')->constrained('caja')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ventas MODIFY metodo_pago ENUM('efectivo','tarjeta','transferencia','yape','plin','mixto') NOT NULL DEFAULT 'efectivo'");
        }

        Schema::create('pagos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->index();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->enum('metodo', ['efectivo', 'tarjeta', 'transferencia', 'yape', 'plin']);
            $table->decimal('monto', 10, 2);
            $table->string('referencia', 100)->nullable();
            $table->timestamps();
            $table->index(['tienda_id', 'metodo', 'created_at'], 'pagos_tienda_metodo_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_venta');
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ventas MODIFY metodo_pago ENUM('efectivo','tarjeta','transferencia','yape','plin') NOT NULL DEFAULT 'efectivo'");
        }
        Schema::table('ventas', fn (Blueprint $table) => $table->dropConstrainedForeignId('caja_id'));
    }
};
