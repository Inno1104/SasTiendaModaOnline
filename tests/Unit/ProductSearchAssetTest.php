<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductSearchAssetTest extends TestCase
{
    public function test_product_listing_uses_aggregated_stock_instead_of_per_row_queries(): void
    {
        $view = file_get_contents(resource_path('views/productos/index.blade.php'));

        $this->assertStringNotContainsString('stockTotal()', $view);
        $this->assertStringContainsString('$p->stock_total', $view);
        $this->assertStringContainsString('$p->variantes_stock_bajo', $view);
    }
}
