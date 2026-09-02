<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->timestamp('reserva_expira_at')->nullable()->after('simbolo_moneda');
            $table->index(['tienda_id', 'estado', 'reserva_expira_at'], 'pedido_online_reserva_vencimiento');
        });

        Schema::create('stock_reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->foreignId('pedido_online_id')->constrained('pedidos_online')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes')->restrictOnDelete();
            $table->unsignedInteger('cantidad');
            $table->string('estado', 20)->default('activa');
            $table->timestamp('expira_at');
            $table->timestamp('liberada_at')->nullable();
            $table->timestamp('consumida_at')->nullable();
            $table->string('motivo_liberacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['pedido_online_id', 'variante_id'], 'stock_reserva_pedido_variante_unica');
            $table->index(['tienda_id', 'variante_id', 'estado', 'expira_at'], 'stock_reserva_disponibilidad');
            $table->index(['estado', 'expira_at', 'id'], 'stock_reserva_vencimiento');
        });

        $this->addIntegrityConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservas');
        Schema::table('pedidos_online', function (Blueprint $table) {
            $table->dropIndex('pedido_online_reserva_vencimiento');
            $table->dropColumn('reserva_expira_at');
        });
    }

    private function addIntegrityConstraints(): void
    {
        $expression = "cantidad > 0 AND estado IN ('activa','consumida','liberada','vencida') AND ((estado = 'activa' AND liberada_at IS NULL AND consumida_at IS NULL) OR (estado = 'consumida' AND consumida_at IS NOT NULL AND liberada_at IS NULL) OR (estado IN ('liberada','vencida') AND liberada_at IS NOT NULL AND consumida_at IS NULL))";

        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement("ALTER TABLE stock_reservas ADD CONSTRAINT stock_reserva_integridad CHECK ({$expression})");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $qualified = preg_replace('/\b(cantidad|estado|liberada_at|consumida_at)\b/', 'NEW.$1', $expression);
            DB::unprepared("CREATE TRIGGER stock_reservas_integridad_insert BEFORE INSERT ON stock_reservas WHEN NOT ({$qualified}) BEGIN SELECT RAISE(ABORT, 'Reserva de stock inconsistente'); END");
            DB::unprepared("CREATE TRIGGER stock_reservas_integridad_update BEFORE UPDATE ON stock_reservas WHEN NOT ({$qualified}) BEGIN SELECT RAISE(ABORT, 'Reserva de stock inconsistente'); END");
        }
    }
};
