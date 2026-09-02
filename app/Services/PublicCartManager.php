<?php

namespace App\Services;

use App\Models\ProductoVariante;
use App\Models\StockReserva;
use App\Models\Tienda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PublicCartManager
{
    public const MAX_QUANTITY_PER_LINE = 99;

    public const MAX_DISTINCT_ITEMS = 50;

    /**
     * @return array{items:Collection<int,array<string,mixed>>,units:int,subtotal:float,adjusted:bool}
     */
    public function snapshot(Tienda $store, SessionStore $session): array
    {
        $stored = $this->storedItems($store, $session);
        if ($stored === []) {
            return $this->emptySnapshot();
        }

        $variants = $this->availableVariants($store, array_keys($stored))->keyBy('id');
        $normalized = [];
        $items = collect();
        $adjusted = false;

        foreach ($stored as $variantId => $requestedQuantity) {
            $variant = $variants->get($variantId);
            $availableStock = $variant?->stockDisponibleVenta() ?? 0;
            if (! $variant || $availableStock < 1) {
                $adjusted = true;
                continue;
            }

            $quantity = min($requestedQuantity, $availableStock, self::MAX_QUANTITY_PER_LINE);
            $adjusted = $adjusted || $quantity !== $requestedQuantity;
            $normalized[$variantId] = $quantity;
            $unitPrice = round($variant->producto->precioActual(), 2);
            $items->push([
                'variant_id' => (int) $variant->id,
                'product_id' => (int) $variant->producto->id,
                'code' => $variant->producto->codigo,
                'name' => $variant->producto->nombre,
                'image' => $variant->producto->imagen,
                'size' => $variant->talla,
                'color' => $variant->color,
                'stock' => $availableStock,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => round($unitPrice * $quantity, 2),
            ]);
        }

        if ($normalized !== $stored) {
            $this->persist($store, $session, $normalized);
        }

        return [
            'items' => $items,
            'units' => (int) $items->sum('quantity'),
            'subtotal' => round((float) $items->sum('subtotal'), 2),
            'adjusted' => $adjusted,
        ];
    }

    public function add(Tienda $store, SessionStore $session, int $variantId, int $quantity): void
    {
        $variant = $this->variantForStore($store, $variantId);
        $items = $this->storedItems($store, $session);

        if (! array_key_exists($variantId, $items) && count($items) >= self::MAX_DISTINCT_ITEMS) {
            throw ValidationException::withMessages([
                'variante_id' => 'El carrito alcanzó el máximo de productos distintos.',
            ]);
        }

        $newQuantity = ($items[$variantId] ?? 0) + $quantity;
        $this->ensureQuantityIsAvailable($variant, $newQuantity);
        $items[$variantId] = $newQuantity;
        $this->persist($store, $session, $items);
    }

    public function update(Tienda $store, SessionStore $session, int $variantId, int $quantity): void
    {
        $items = $this->storedItems($store, $session);
        if (! array_key_exists($variantId, $items)) {
            throw ValidationException::withMessages([
                'cantidad' => 'La variante no se encuentra en el carrito.',
            ]);
        }

        $variant = $this->variantForStore($store, $variantId);
        $this->ensureQuantityIsAvailable($variant, $quantity);
        $items[$variantId] = $quantity;
        $this->persist($store, $session, $items);
    }

    public function remove(Tienda $store, SessionStore $session, int $variantId): void
    {
        $items = $this->storedItems($store, $session);
        unset($items[$variantId]);
        $this->persist($store, $session, $items);
    }

    public function clear(Tienda $store, SessionStore $session): void
    {
        $session->forget($this->sessionKey($store));
    }

    private function variantForStore(Tienda $store, int $variantId): ProductoVariante
    {
        $variant = $this->availableVariants($store, [$variantId])->first();
        if (! $variant) {
            throw ValidationException::withMessages([
                'variante_id' => 'La variante seleccionada no está disponible en esta tienda.',
            ]);
        }

        return $variant;
    }

    /** @param array<int,int> $variantIds */
    private function availableVariants(Tienda $store, array $variantIds): Collection
    {
        return ProductoVariante::withoutGlobalScopes()
            ->select(['id', 'tienda_id', 'producto_id', 'talla', 'color', 'stock'])
            ->withSum(['reservas as stock_reservado' => fn ($reservations) => $reservations
                ->withoutGlobalScopes()
                ->where('estado', StockReserva::ACTIVE)
                ->where('expira_at', '>', now())], 'cantidad')
            ->where('tienda_id', $store->id)
            ->whereIn('id', $variantIds)
            ->whereHas('producto', fn (Builder $products) => $products
                ->withoutGlobalScopes()
                ->where('tienda_id', $store->id)
                ->where('activo', true)
                ->whereHas('categoria', fn (Builder $categories) => $categories
                    ->withoutGlobalScopes()
                    ->where('tienda_id', $store->id)
                    ->where('activo', true)))
            ->with(['producto' => fn ($products) => $products
                ->withoutGlobalScopes()
                ->select(['id', 'tienda_id', 'categoria_id', 'codigo', 'nombre', 'precio_venta', 'precio_oferta', 'imagen'])])
            ->get();
    }

    private function ensureQuantityIsAvailable(ProductoVariante $variant, int $quantity): void
    {
        $availableStock = $variant->stockDisponibleVenta();
        if ($quantity > min($availableStock, self::MAX_QUANTITY_PER_LINE)) {
            throw ValidationException::withMessages([
                'cantidad' => "Solo hay {$availableStock} unidades disponibles para esta talla y color.",
            ]);
        }
    }

    /** @return array<int,int> */
    private function storedItems(Tienda $store, SessionStore $session): array
    {
        $value = $session->get($this->sessionKey($store), []);
        if (! is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $variantId => $quantity) {
            if (filter_var($variantId, FILTER_VALIDATE_INT) === false || filter_var($quantity, FILTER_VALIDATE_INT) === false) {
                continue;
            }

            $variantId = (int) $variantId;
            $quantity = (int) $quantity;
            if ($variantId > 0 && $quantity > 0) {
                $items[$variantId] = min($quantity, self::MAX_QUANTITY_PER_LINE);
            }
        }

        return array_slice($items, 0, self::MAX_DISTINCT_ITEMS, true);
    }

    /** @param array<int,int> $items */
    private function persist(Tienda $store, SessionStore $session, array $items): void
    {
        if ($items === []) {
            $session->forget($this->sessionKey($store));

            return;
        }

        $session->put($this->sessionKey($store), $items);
    }

    private function sessionKey(Tienda $store): string
    {
        return 'public_cart_store_'.$store->id;
    }

    /** @return array{items:Collection<int,array<string,mixed>>,units:int,subtotal:float,adjusted:bool} */
    private function emptySnapshot(): array
    {
        return ['items' => collect(), 'units' => 0, 'subtotal' => 0.0, 'adjusted' => false];
    }
}
