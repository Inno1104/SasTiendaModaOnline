<?php

namespace Tests\Unit;

use Tests\TestCase;

final class StorefrontCatalogAssetTest extends TestCase
{
    public function test_catalog_card_exposes_real_variant_controls_and_external_script(): void
    {
        $view = file_get_contents(resource_path('views/storefront/show.blade.php'));
        $script = file_get_contents(public_path('js/storefront-catalog.js'));

        $this->assertStringContainsString("asset('js/storefront-catalog.js')", $view);
        $this->assertStringContainsString('data-variant-color', $view);
        $this->assertStringContainsString('data-variant-size', $view);
        $this->assertStringContainsString('data-variant-select', $view);
        $this->assertStringContainsString('data-quantity-minus', $view);
        $this->assertStringNotContainsString('onchange=', $view);
        $this->assertStringContainsString('option.dataset.stock', $script);
        $this->assertStringContainsString('availableOptions()', $script);
        $this->assertStringContainsString('chooseBySize', $script);
        $this->assertStringContainsString('chooseByColor', $script);
        $this->assertStringContainsString("option.dataset.color === color", $script);
    }
}
