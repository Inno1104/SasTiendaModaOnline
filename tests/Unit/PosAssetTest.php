<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PosAssetTest extends TestCase
{
    public function test_pos_uses_local_separated_assets(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/ventas/create.blade.php');

        $this->assertFileExists(__DIR__.'/../../public/css/pos.css');
        $this->assertFileExists(__DIR__.'/../../public/js/pos.js');
        $this->assertStringContainsString("asset('css/pos.css')", $view);
        $this->assertStringContainsString("asset('js/pos.js')", $view);
        $this->assertStringNotContainsString('function addSelectedVariant', $view);
        $this->assertStringContainsString('category-rail', $view);
        $this->assertStringContainsString('data-category-shortcut', $view);
        $this->assertStringContainsString('cartBubble', $view);
        $this->assertStringContainsString('catalog-heading', $view);
    }

    public function test_pos_script_contains_the_critical_interaction_guards(): void
    {
        $script = file_get_contents(__DIR__.'/../../public/js/pos.js');

        $this->assertStringContainsString('if (submitting) return', $script);
        $this->assertStringContainsString('variantId', $script);
        $this->assertStringContainsString('item.quantity >= item.stock', $script);
        $this->assertStringContainsString('showModal', $script);
        $this->assertStringContainsString('data-discount', $script);
        $this->assertStringContainsString('cart-item-thumb', $script);
        $this->assertStringContainsString('syncCategoryShortcuts', $script);
        $this->assertStringContainsString('button.dataset.image', $script);
        $this->assertStringContainsString("element.setAttribute('role', error ? 'alert' : 'status')", $script);
        $this->assertStringContainsString('if (!checkoutIsValid()) return', $script);
        $this->assertStringContainsString("window.addEventListener('pageshow'", $script);
        $this->assertStringContainsString("setAttribute('aria-busy', 'true')", $script);
    }

    public function test_pos_styles_define_tablet_and_mobile_layouts(): void
    {
        $styles = file_get_contents(__DIR__.'/../../public/css/pos.css');

        $this->assertStringContainsString('@media(max-width:900px)', $styles);
        $this->assertStringContainsString('@media(max-width:600px)', $styles);
        $this->assertStringContainsString('.pos-layout', $styles);
        $this->assertStringContainsString('.category-rail', $styles);
        $this->assertStringContainsString('.pos-title-icon', $styles);
        $this->assertStringContainsString('.cart-item-thumb', $styles);
        $this->assertStringContainsString('.catalog-heading', $styles);
        $this->assertStringContainsString('repeat(4,minmax(0,1fr))', $styles);
    }

    public function test_performance_migration_defines_pos_search_indexes(): void
    {
        $migration = file_get_contents(__DIR__.'/../../database/migrations/2026_08_24_000007_add_sales_audit_and_performance_indexes.php');

        $this->assertStringContainsString('clientes_busqueda_pos', $migration);
        $this->assertStringContainsString('productos_busqueda_pos', $migration);
        $this->assertStringContainsString('variantes_codigo_barra_tienda', $migration);
        $this->assertStringContainsString('caja_estado_tienda', $migration);
    }

    public function test_sales_production_checklist_documents_deployment_and_recovery(): void
    {
        $checklist = file_get_contents(__DIR__.'/../../docs/sales-production-checklist.md');

        $this->assertStringContainsString('php artisan migrate --force', $checklist);
        $this->assertStringContainsString('dos workers', $checklist);
        $this->assertStringContainsString('restaur', mb_strtolower($checklist));
        $this->assertStringContainsString('UUID', $checklist);
    }
}
