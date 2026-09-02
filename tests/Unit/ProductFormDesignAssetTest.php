<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductFormDesignAssetTest extends TestCase
{
    public function test_create_form_uses_shared_assets_and_preserves_variant_contract(): void
    {
        $view = file_get_contents($this->projectPath('resources/views/productos/create.blade.php'));

        $this->assertStringContainsString("asset('css/product-form.css')", $view);
        $this->assertStringContainsString("asset('js/product-form.js')", $view);
        $this->assertStringContainsString("asset('js/product-image.js')", $view);
        $this->assertStringContainsString('variantes[{{ $index }}][codigo_barra]', $view);
        $this->assertStringContainsString("old('variantes'", $view);
        $this->assertStringNotContainsString('onclick=', $view);
        $this->assertStringNotContainsString('<script>', $view);
    }

    public function test_product_form_assets_define_feedback_and_responsive_layouts(): void
    {
        $styles = file_get_contents($this->projectPath('public/css/product-form.css'));
        $script = file_get_contents($this->projectPath('public/js/product-form.js'));

        $this->assertStringContainsString('@media(max-width:1100px)', $styles);
        $this->assertStringContainsString('@media(max-width:760px)', $styles);
        $this->assertStringContainsString('data-variant-state', $script);
        $this->assertStringContainsString('setCustomValidity', $script);
        $this->assertStringContainsString('totalStock', $script);
        $this->assertStringContainsString('toLocaleUpperCase', $script);
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
