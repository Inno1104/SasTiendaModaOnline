<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private array $types = [
        'entrada', 'salida', 'ajuste', 'venta', 'compra', 'conciliacion', 'anulacion', 'devolucion',
    ];

    public function up(): void
    {
        $this->assertExistingDataIsValid();

        match (DB::getDriverName()) {
            'mysql' => $this->createMysqlChecks(),
            'pgsql' => $this->createPostgresChecks(),
            'sqlite' => $this->createSqliteTrigger(),
            default => null,
        };
    }

    public function down(): void
    {
        match (DB::getDriverName()) {
            'mysql' => $this->dropMysqlChecks(),
            'pgsql' => $this->dropPostgresChecks(),
            'sqlite' => DB::unprepared('DROP TRIGGER IF EXISTS stock_movimientos_integridad_insert'),
            default => null,
        };
    }

    private function assertExistingDataIsValid(): void
    {
        if (DB::table('stock_movimientos')->whereRaw('NOT ('.$this->integrityExpression().')')->exists()) {
            throw new RuntimeException(
                'Existen movimientos de stock inconsistentes. Corrija el Kardex antes de aplicar las restricciones.'
            );
        }
    }

    private function createMysqlChecks(): void
    {
        foreach ($this->checks() as $name => $expression) {
            DB::statement("ALTER TABLE stock_movimientos ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    private function dropMysqlChecks(): void
    {
        foreach (array_reverse(array_keys($this->checks())) as $name) {
            DB::statement("ALTER TABLE stock_movimientos DROP CHECK {$name}");
        }
    }

    private function createPostgresChecks(): void
    {
        foreach ($this->checks() as $name => $expression) {
            DB::statement("ALTER TABLE stock_movimientos ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    private function dropPostgresChecks(): void
    {
        foreach (array_reverse(array_keys($this->checks())) as $name) {
            DB::statement("ALTER TABLE stock_movimientos DROP CONSTRAINT {$name}");
        }
    }

    private function createSqliteTrigger(): void
    {
        $expression = $this->integrityExpression('NEW.');
        DB::unprepared("CREATE TRIGGER stock_movimientos_integridad_insert
            BEFORE INSERT ON stock_movimientos
            WHEN NOT ({$expression})
            BEGIN
                SELECT RAISE(ABORT, 'Movimiento de stock inconsistente');
            END");
    }

    /** @return array<string, string> */
    private function checks(string $prefix = ''): array
    {
        $types = "'".implode("','", $this->types)."'";
        $stockBefore = $prefix.'stock_anterior';
        $quantity = $prefix.'cantidad';
        $stockAfter = $prefix.'stock_resultante';
        $type = $prefix.'tipo';
        $direction = $prefix.'direccion';
        $signedType = match (DB::getDriverName()) {
            'pgsql' => 'BIGINT',
            'sqlite' => 'INTEGER',
            default => 'SIGNED',
        };

        return [
            'stock_mov_tipo_valido' => "{$type} IN ({$types})",
            'stock_mov_direccion_valida' => "{$direction} IN ('entrada','salida')",
            'stock_mov_aritmetica_valida' => "{$quantity} <> 0 AND CAST({$stockBefore} AS {$signedType}) + {$quantity} = {$stockAfter} AND (({$direction} = 'entrada' AND {$quantity} > 0) OR ({$direction} = 'salida' AND {$quantity} < 0))",
            'stock_mov_tipo_direccion_valida' => "(({$type} IN ('entrada','compra','devolucion') AND {$direction} = 'entrada') OR ({$type} IN ('salida','venta') AND {$direction} = 'salida') OR {$type} IN ('ajuste','conciliacion','anulacion'))",
        ];
    }

    private function integrityExpression(string $prefix = ''): string
    {
        return implode(' AND ', array_map(fn (string $check) => "({$check})", $this->checks($prefix)));
    }
};
