<?php

namespace Tests\Unit;

use Tests\TestCase;

class StockMovementIntegrityMigrationTest extends TestCase
{
    public function test_migration_defines_type_direction_arithmetic_and_semantic_constraints(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_08_26_000046_enforce_stock_movement_integrity.php'
        ));

        $this->assertStringContainsString('stock_mov_tipo_valido', $migration);
        $this->assertStringContainsString('stock_mov_direccion_valida', $migration);
        $this->assertStringContainsString('stock_mov_aritmetica_valida', $migration);
        $this->assertStringContainsString('stock_mov_tipo_direccion_valida', $migration);
        $this->assertStringContainsString("'entrada', 'salida', 'ajuste', 'venta', 'compra', 'conciliacion', 'anulacion', 'devolucion'", $migration);
        $this->assertStringContainsString('{$quantity} <> 0', $migration);
        $this->assertStringContainsString('{$stockBefore} AS {$signedType}', $migration);
    }
}
