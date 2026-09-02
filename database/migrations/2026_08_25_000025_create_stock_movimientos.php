<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 30);
            $table->string('direccion', 10);
            $table->unsignedInteger('stock_anterior');
            $table->integer('cantidad');
            $table->unsignedInteger('stock_resultante');
            $table->nullableMorphs('referencia');
            $table->string('motivo', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tienda_id', 'variante_id', 'created_at'], 'stock_mov_tienda_variante_fecha');
            $table->index(['tienda_id', 'tipo', 'created_at'], 'stock_mov_tienda_tipo_fecha');
        });

        match (DB::getDriverName()) {
            'mysql' => $this->createMysqlImmutabilityTriggers(),
            'pgsql' => $this->createPostgresImmutabilityTrigger(),
            'sqlite' => $this->createSqliteImmutabilityTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->dropMysqlImmutabilityTriggers(),
            'pgsql' => $this->dropPostgresImmutabilityTrigger(),
            'sqlite' => $this->dropSqliteImmutabilityTriggers(),
            default => null,
        };

        Schema::dropIfExists('stock_movimientos');
    }

    private function createMysqlImmutabilityTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER stock_movimientos_no_update BEFORE UPDATE ON stock_movimientos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de stock son inmutables'");
        DB::unprepared("CREATE TRIGGER stock_movimientos_no_delete BEFORE DELETE ON stock_movimientos FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Los movimientos de stock son inmutables'");
    }

    private function dropMysqlImmutabilityTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_no_delete');
    }

    private function createPostgresImmutabilityTrigger(): void
    {
        DB::unprepared("CREATE FUNCTION prevent_stock_movement_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'Los movimientos de stock son inmutables'; END; $$ LANGUAGE plpgsql");
        DB::unprepared('CREATE TRIGGER stock_movimientos_no_mutation BEFORE UPDATE OR DELETE ON stock_movimientos FOR EACH ROW EXECUTE FUNCTION prevent_stock_movement_mutation()');
    }

    private function dropPostgresImmutabilityTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_no_mutation ON stock_movimientos');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_stock_movement_mutation()');
    }

    private function createSqliteImmutabilityTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER stock_movimientos_no_update BEFORE UPDATE ON stock_movimientos BEGIN SELECT RAISE(ABORT, 'Los movimientos de stock son inmutables'); END");
        DB::unprepared("CREATE TRIGGER stock_movimientos_no_delete BEFORE DELETE ON stock_movimientos BEGIN SELECT RAISE(ABORT, 'Los movimientos de stock son inmutables'); END");
    }

    private function dropSqliteImmutabilityTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_no_delete');
    }
};
