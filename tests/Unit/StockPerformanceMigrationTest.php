<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StockPerformanceMigrationTest extends TestCase
{
    public function test_stock_indexes_match_real_filters_and_stable_order(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_25_000027_optimize_stock_catalog_queries.php'
        );

        $this->assertStringContainsString("['tienda_id', 'stock', 'id']", $migration);
        $this->assertStringContainsString("['tienda_id', 'activo', 'categoria_id', 'id']", $migration);
        $this->assertStringNotContainsString("['tienda_id', 'talla']", $migration);
        $this->assertStringNotContainsString("['tienda_id', 'color']", $migration);
    }
}
