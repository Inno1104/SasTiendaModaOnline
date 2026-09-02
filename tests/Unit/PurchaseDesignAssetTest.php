<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseDesignAssetTest extends TestCase
{
    public function test_purchase_views_use_shared_assets_without_inline_presentation_or_handlers(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['index', 'create', 'show'] as $view) {
            $contents = file_get_contents($root."/resources/views/compras/{$view}.blade.php");
            $this->assertStringNotContainsString('style="', $contents);
            $this->assertStringNotContainsString('onclick=', $contents);
            $this->assertStringNotContainsString('onchange=', $contents);
            $this->assertStringContainsString("asset('css/compras.css')", $contents);
        }

        $create = file_get_contents($root.'/resources/views/compras/create.blade.php');
        $this->assertStringContainsString('<x-purchases.form', $create);
        $this->assertStringContainsString("asset('js/compras.js')", $create);
    }

    public function test_reusable_form_preserves_business_contract_and_accessibility(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/purchases/form.blade.php');

        foreach (['operacion_id', 'proveedor_id', 'fecha', 'observaciones', 'items[__INDEX__][variante_id]', 'items[__INDEX__][cantidad]', 'items[__INDEX__][precio]'] as $field) {
            $this->assertStringContainsString($field, $form);
        }
        foreach (['role="alert"', 'aria-live="polite"', 'aria-labelledby=', 'aria-describedby=', 'data-purchase-submit'] as $token) {
            $this->assertStringContainsString($token, $form);
        }
        $this->assertStringNotContainsString('style="', $form);
    }

    public function test_assets_define_responsive_states_and_safe_interactions(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root.'/public/css/compras.css');
        $script = file_get_contents($root.'/public/js/compras.js');

        foreach (['purchase-filter-grid', 'purchase-product-picker', '@media (max-width: 760px)', 'prefers-reduced-motion', '.sr-only'] as $token) {
            $this->assertStringContainsString($token, $css);
        }
        foreach (['fetch(url', 'textContent', 'replaceChildren', "addEventListener('submit'", "setAttribute('aria-busy'", 'data-item-template'] as $token) {
            $this->assertStringContainsString($token, $script);
        }
        $this->assertStringNotContainsString('innerHTML', $script);
    }
}
