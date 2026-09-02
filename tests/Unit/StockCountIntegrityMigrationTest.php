<?php

namespace Tests\Unit;

use Tests\TestCase;

class StockCountIntegrityMigrationTest extends TestCase
{
    public function test_migration_defines_identity_state_trace_and_immutability_guards(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_08_26_000047_harden_stock_counts.php'
        ));

        foreach (['stock_conteos_estado_valido', 'stock_conteos_diferencia_valida',
            'stock_conteos_identidad_valida', 'stock_conteos_estado_metadata_valida',
            'conteos_tienda_operacion_unica', 'stock_conteos_terminal_no_update',
            'stock_conteos_terminal_no_delete'] as $guard) {
            $this->assertStringContainsString($guard, $migration);
        }
    }
}
