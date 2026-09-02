<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CategoryPerformanceMigrationTest extends TestCase
{
    public function test_category_query_indexes_cover_tenant_filters_and_dependencies(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_24_000021_optimize_category_queries.php'
        );

        $this->assertStringContainsString('categorias_tienda_estado_nombre', $migration);
        $this->assertStringContainsString('categorias_tienda_genero_nombre', $migration);
        $this->assertStringContainsString('subcategorias_categoria_estado', $migration);
    }
}
