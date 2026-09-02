<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandPerformanceMigrationTest extends TestCase
{
    public function test_brand_query_indexes_cover_tenant_filters_and_product_counts(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_24_000023_optimize_brand_queries.php'
        );

        $this->assertStringContainsString("['tienda_id', 'activo', 'nombre_normalizado', 'id']", $migration);
        $this->assertStringContainsString("['tienda_id', 'created_at', 'id']", $migration);
        $this->assertStringContainsString("['tienda_id', 'marca_id', 'activo']", $migration);
    }
}
