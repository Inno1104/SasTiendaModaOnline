<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CategoryDesignAssetTest extends TestCase
{
    public function test_category_view_uses_shared_assets_without_inline_presentation_or_handlers(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root.'/resources/views/categorias/index.blade.php');

        $this->assertStringContainsString("asset('css/categorias.css')", $view);
        $this->assertStringContainsString("asset('js/categorias.js')", $view);
        $this->assertStringNotContainsString('style=', $view);
        $this->assertStringNotContainsString('onsubmit=', $view);
    }

    public function test_category_design_has_responsive_accessible_states(): void
    {
        $root = dirname(__DIR__, 2);
        $css = file_get_contents($root.'/public/css/categorias.css');
        $script = file_get_contents($root.'/public/js/categorias.js');

        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.category-empty-result', $css);
        $this->assertStringContainsString('[data-category-delete]', $script);
        $this->assertStringContainsString('hasDependencies', $script);
    }
}
