<?php

namespace Tests\Unit;

use App\Models\ProductoVariante;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProductVariantAvailabilityTest extends TestCase
{
    #[DataProvider('stockStates')]
    public function test_variant_stock_state(
        int $stock,
        int $minimum,
        string $expectedState,
        bool $exhausted,
        bool $low
    ): void {
        $variant = new ProductoVariante([
            'stock' => $stock,
            'stock_minimo' => $minimum,
        ]);

        $this->assertSame($expectedState, $variant->estadoStock());
        $this->assertSame($exhausted, $variant->estaAgotada());
        $this->assertSame($low, $variant->tieneStockBajo());
    }

    public static function stockStates(): array
    {
        return [
            'agotada' => [0, 5, 'agotada', true, false],
            'stock bajo en el límite' => [5, 5, 'bajo', false, true],
            'disponible sobre el mínimo' => [6, 5, 'disponible', false, false],
            'sin mínimo configurado' => [1, 0, 'disponible', false, false],
        ];
    }

    public function test_stock_description_is_explicit(): void
    {
        $exhausted = new ProductoVariante(['stock' => 0, 'stock_minimo' => 2]);
        $low = new ProductoVariante(['stock' => 2, 'stock_minimo' => 2]);
        $available = new ProductoVariante(['stock' => 8, 'stock_minimo' => 2]);

        $this->assertSame('Agotada', $exhausted->descripcionStock());
        $this->assertSame('2 disponibles · Stock bajo', $low->descripcionStock());
        $this->assertSame('8 disponibles', $available->descripcionStock());
    }
}
