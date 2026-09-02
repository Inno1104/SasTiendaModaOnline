<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductImageAssetTest extends TestCase
{
    public function test_product_forms_share_the_same_image_preview_script(): void
    {
        $create = file_get_contents($this->projectPath('resources/views/productos/create.blade.php'));
        $edit = file_get_contents($this->projectPath('resources/views/productos/edit.blade.php'));
        $script = file_get_contents($this->projectPath('public/js/product-image.js'));

        $this->assertStringContainsString("asset('js/product-image.js')", $create);
        $this->assertStringContainsString("asset('js/product-image.js')", $edit);
        $this->assertStringNotContainsString('new FileReader', $create);
        $this->assertStringNotContainsString('URL.createObjectURL', $edit);
        $this->assertStringContainsString("['image/jpeg', 'image/png', 'image/webp']", $script);
        $this->assertStringContainsString('4 * 1024 * 1024', $script);
    }

    public function test_product_images_have_accessible_alternative_text(): void
    {
        foreach (['index.blade.php', 'show.blade.php', 'edit.blade.php'] as $view) {
            $contents = file_get_contents($this->projectPath('resources/views/productos/'.$view));
            $this->assertMatchesRegularExpression('/<img[^\r\n]*\salt=/', $contents);
        }
    }

    private function projectPath(string $path): string
    {
        return dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
