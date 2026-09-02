<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductPerformanceMigrationTest extends TestCase
{
    public function test_product_catalog_migration_defines_filter_and_order_indexes(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_24_000018_optimize_product_catalog_queries.php'));

        $this->assertStringContainsString('productos_catalogo_tienda_fecha', $migration);
        $this->assertStringContainsString('productos_catalogo_categoria_fecha', $migration);
        $this->assertStringContainsString('productos_catalogo_genero_tipo_fecha', $migration);
        $this->assertStringContainsString('productos_catalogo_tipo_fecha', $migration);
        $this->assertStringContainsString("['tienda_id', 'created_at', 'id']", $migration);
    }
}
