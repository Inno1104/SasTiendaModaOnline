<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ClientePerformanceAssetTest extends TestCase
{
    public function test_customer_performance_migration_defines_filter_and_relation_indexes(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_24_000015_optimize_customer_queries.php');

        $this->assertStringContainsString('clientes_segmento_ciudad_estado', $migration);
        $this->assertStringContainsString('clientes_registro_tienda_fecha', $migration);
        $this->assertStringContainsString('ventas_cliente_estado_fecha', $migration);
        $this->assertStringContainsString('ventas_cliente_historial', $migration);
    }

    public function test_customer_export_uses_a_cursor_instead_of_loading_every_record(): void
    {
        $controller = file_get_contents(__DIR__.'/../../app/Http/Controllers/ClienteController.php');

        $this->assertStringContainsString('foreach ($customers->cursor() as $customer)', $controller);
        $this->assertStringNotContainsString('$customers->get()', $controller);
    }
}
