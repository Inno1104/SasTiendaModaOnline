<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class StorefrontProductAssetTest extends TestCase
{
    public function test_gallery_prefers_selected_color_and_only_uses_generic_images_as_fallback(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/storefront-product.js');

        $this->assertStringContainsString('selectedColorImages', $script);
        $this->assertStringContainsString('matching.length ? matching', $script);
        $this->assertStringNotContainsString('[...exact, ...colorImages, ...anyColorImages, ...generic]', $script);
    }

    public function test_legacy_combined_sizes_are_not_exposed_as_a_public_variant(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/storefront/product.blade.php');

        $this->assertStringContainsString("preg_match('/[,;|]+/u'", $view);
    }

    public function test_product_detail_uses_marketplace_purchase_selector(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/storefront/product.blade.php');
        $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/storefront-product-marketplace.css');
        $script = file_get_contents(dirname(__DIR__, 2).'/public/js/storefront-product.js');

        $this->assertStringContainsString('product-offer-panel', $view);
        $this->assertStringContainsString('data-size-guide', $view);
        $this->assertStringContainsString('data-fit-note', $view);
        $this->assertStringContainsString('related-products-grid', $view);
        $this->assertStringContainsString('--store-color:', $view);
        $this->assertStringContainsString("'sizeDetail' => \$variant->detalle_talla", $view);
        $this->assertStringContainsString('--offer-accent', $styles);
        $this->assertStringContainsString('fitNote.textContent', $script);
        $this->assertStringContainsString('variant.sizeDetail', $script);
    }
}
