<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ProductPlanConcurrencyAssetTest extends TestCase
{
    public function test_product_capacity_is_checked_while_store_row_is_locked(): void
    {
        $manager = file_get_contents(app_path('Services/ProductoManager.php'));

        $lockPosition = strpos($manager, "Tienda::query()->with('plan')->lockForUpdate()");
        $guardPosition = strpos($manager, '$this->planGuard->ensureActiveSlot($store)');
        $createPosition = strpos($manager, '$product = Producto::create($data)');

        $this->assertNotFalse($lockPosition);
        $this->assertNotFalse($guardPosition);
        $this->assertNotFalse($createPosition);
        $this->assertLessThan($guardPosition, $lockPosition);
        $this->assertLessThan($createPosition, $guardPosition);
    }
}
