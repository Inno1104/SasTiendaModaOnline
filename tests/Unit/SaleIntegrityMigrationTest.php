<?php

namespace Tests\Unit;

use Tests\TestCase;

class SaleIntegrityMigrationTest extends TestCase
{
    public function test_sales_integrity_migration_defines_required_database_checks(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_08_25_000040_enforce_sale_money_integrity.php'
        ));

        $this->assertStringContainsString('ventas_importes_validos', $migration);
        $this->assertStringContainsString('detalle_ventas_importes_validos', $migration);
        $this->assertStringContainsString('pagos_venta_monto_valido', $migration);
        $this->assertStringContainsString('cantidad > 0', $migration);
        $this->assertStringContainsString('monto > 0', $migration);
    }
}
