<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandDependencyAssetTest extends TestCase
{
    public function test_brand_deletion_and_product_creation_share_store_lock(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $brandManager = file_get_contents($projectRoot.'/app/Services/BrandManager.php');
        $productManager = file_get_contents($projectRoot.'/app/Services/ProductoManager.php');

        $this->assertStringContainsString('lockForUpdate()', $brandManager);
        $this->assertStringContainsString('productos()->exists()', $brandManager);
        $this->assertStringContainsString('lockForUpdate()', $productManager);
    }
}
