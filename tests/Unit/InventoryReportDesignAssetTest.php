<?php

namespace Tests\Unit;

use Tests\TestCase;

class InventoryReportDesignAssetTest extends TestCase
{
    public function test_inventory_report_uses_an_independent_responsive_stylesheet(): void
    {
        $view = file_get_contents(resource_path('views/reportes/inventario.blade.php'));
        $styles = file_get_contents(public_path('css/inventory-report.css'));

        $this->assertStringContainsString("asset('css/inventory-report.css')", $view);
        $this->assertStringContainsString('inventory-filter-card', $view);
        $this->assertStringContainsString('inventory-metrics', $view);
        $this->assertStringContainsString('inventory-table-card', $view);
        $this->assertStringContainsString('@media(max-width:640px)', $styles);
        $this->assertStringContainsString('.inventory-status.is-available', $styles);
        $this->assertStringContainsString('.inventory-status.is-low', $styles);
        $this->assertStringContainsString('.inventory-status.is-out', $styles);
        $this->assertStringContainsString('@media(min-width:1451px)', $styles);
        $this->assertStringContainsString('repeat(4,minmax(145px,.85fr)) auto', $styles);
    }
}
