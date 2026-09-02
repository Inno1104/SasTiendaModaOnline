<?php

use App\Models\ProductoVariante;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $invalidExists = DB::table('producto_variantes')
            ->where(function ($query): void {
                $query->where('stock', '<', ProductoVariante::MIN_STOCK)
                    ->orWhere('stock', '>', ProductoVariante::MAX_STOCK)
                    ->orWhere('stock_minimo', '<', ProductoVariante::MIN_STOCK)
                    ->orWhere('stock_minimo', '>', ProductoVariante::MAX_STOCK);
            })
            ->exists();

        if ($invalidExists) {
            throw new RuntimeException(
                'Existen variantes con stock fuera del rango permitido. Corríjalas antes de ejecutar esta migración.'
            );
        }

        match (DB::getDriverName()) {
            'mysql' => $this->addMysqlConstraints(),
            'pgsql' => $this->addPostgresConstraints(),
            'sqlite' => $this->addSqliteTriggers(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->dropMysqlConstraints(),
            'pgsql' => $this->dropPostgresConstraints(),
            'sqlite' => $this->dropSqliteTriggers(),
            default => null,
        };
    }

    private function addMysqlConstraints(): void
    {
        DB::statement('ALTER TABLE producto_variantes ADD CONSTRAINT variantes_stock_rango CHECK (stock BETWEEN 0 AND 2000000000)');
        DB::statement('ALTER TABLE producto_variantes ADD CONSTRAINT variantes_stock_minimo_rango CHECK (stock_minimo BETWEEN 0 AND 2000000000)');
    }

    private function dropMysqlConstraints(): void
    {
        DB::statement('ALTER TABLE producto_variantes DROP CHECK variantes_stock_rango');
        DB::statement('ALTER TABLE producto_variantes DROP CHECK variantes_stock_minimo_rango');
    }

    private function addPostgresConstraints(): void
    {
        DB::statement('ALTER TABLE producto_variantes ADD CONSTRAINT variantes_stock_rango CHECK (stock BETWEEN 0 AND 2000000000)');
        DB::statement('ALTER TABLE producto_variantes ADD CONSTRAINT variantes_stock_minimo_rango CHECK (stock_minimo BETWEEN 0 AND 2000000000)');
    }

    private function dropPostgresConstraints(): void
    {
        DB::statement('ALTER TABLE producto_variantes DROP CONSTRAINT variantes_stock_rango');
        DB::statement('ALTER TABLE producto_variantes DROP CONSTRAINT variantes_stock_minimo_rango');
    }

    private function addSqliteTriggers(): void
    {
        DB::unprepared('CREATE TRIGGER variantes_stock_rango_insert BEFORE INSERT ON producto_variantes
            WHEN NEW.stock < 0 OR NEW.stock > 2000000000 OR NEW.stock_minimo < 0 OR NEW.stock_minimo > 2000000000
            BEGIN SELECT RAISE(ABORT, \'stock fuera de rango\'); END');
        DB::unprepared('CREATE TRIGGER variantes_stock_rango_update BEFORE UPDATE OF stock, stock_minimo ON producto_variantes
            WHEN NEW.stock < 0 OR NEW.stock > 2000000000 OR NEW.stock_minimo < 0 OR NEW.stock_minimo > 2000000000
            BEGIN SELECT RAISE(ABORT, \'stock fuera de rango\'); END');
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS variantes_stock_rango_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS variantes_stock_rango_update');
    }
};
