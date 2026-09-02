<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ventas MODIFY estado ENUM('pendiente','completada','anulada','devuelta') NOT NULL DEFAULT 'completada'");
        }

        Schema::table('ventas', function (Blueprint $table) {
            $table->foreignId('devuelta_por')->nullable()->after('motivo_anulacion')->constrained('users')->nullOnDelete();
            $table->timestamp('devuelta_at')->nullable()->after('devuelta_por');
            $table->string('motivo_devolucion', 500)->nullable()->after('devuelta_at');
            $table->foreignId('caja_devolucion_id')->nullable()->after('motivo_devolucion')->constrained('caja')->nullOnDelete();
        });

        Schema::create('venta_reversiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->restrictOnDelete();
            $table->foreignId('pago_venta_id')->nullable()->constrained('pagos_venta')->restrictOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('caja')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipo', ['anulacion', 'devolucion']);
            $table->enum('metodo', ['efectivo', 'tarjeta', 'transferencia', 'yape', 'plin']);
            $table->decimal('monto', 10, 2);
            $table->string('motivo', 500);
            $table->timestamps();
            $table->index(['tienda_id', 'venta_id', 'created_at'], 'venta_reversion_tienda_venta_fecha');
            $table->index(['tienda_id', 'caja_id', 'created_at'], 'venta_reversion_tienda_caja_fecha');
        });

        Schema::table('movimientos_caja', function (Blueprint $table) {
            $table->foreignId('venta_reversion_id')->nullable()->after('user_id')
                ->constrained('venta_reversiones')->restrictOnDelete();
        });

        $this->createImmutabilityTriggers();
    }

    public function down(): void
    {
        $this->dropImmutabilityTriggers();
        Schema::table('movimientos_caja', fn (Blueprint $table) => $table->dropConstrainedForeignId('venta_reversion_id'));
        Schema::dropIfExists('venta_reversiones');
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caja_devolucion_id');
            $table->dropConstrainedForeignId('devuelta_por');
            $table->dropColumn(['devuelta_at', 'motivo_devolucion']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ventas MODIFY estado ENUM('pendiente','completada','anulada') NOT NULL DEFAULT 'completada'");
        }
    }

    private function createImmutabilityTriggers(): void
    {
        match (DB::getDriverName()) {
            'mysql' => [
                DB::unprepared("CREATE TRIGGER venta_reversiones_no_update BEFORE UPDATE ON venta_reversiones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las reversiones financieras son inmutables'"),
                DB::unprepared("CREATE TRIGGER venta_reversiones_no_delete BEFORE DELETE ON venta_reversiones FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Las reversiones financieras son inmutables'"),
            ],
            'sqlite' => [
                DB::unprepared("CREATE TRIGGER venta_reversiones_no_update BEFORE UPDATE ON venta_reversiones BEGIN SELECT RAISE(ABORT, 'Las reversiones financieras son inmutables'); END"),
                DB::unprepared("CREATE TRIGGER venta_reversiones_no_delete BEFORE DELETE ON venta_reversiones BEGIN SELECT RAISE(ABORT, 'Las reversiones financieras son inmutables'); END"),
            ],
            default => null,
        };
    }

    private function dropImmutabilityTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS venta_reversiones_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS venta_reversiones_no_delete');
    }
};
