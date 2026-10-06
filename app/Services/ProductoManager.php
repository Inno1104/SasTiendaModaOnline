<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\StockMovimiento;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductoManager
{
    public function __construct(
        private readonly ProductCodeGenerator $codeGenerator,
        private readonly ProductImageStorage $images,
        private readonly ProductPlanGuard $planGuard,
        private readonly AuditLogger $audit,
        private readonly InventoryMovementService $stockMovements
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user, ?UploadedFile $image = null, array $gallery = []): Producto
    {
        $storeId = (int) $user->tienda_id;
        $storedGallery = collect($gallery)->groupBy(fn (array $item) => mb_strtolower(trim((string) ($item['talla'] ?? '')).'|'.trim((string) ($item['color'] ?? ''))))
            ->flatMap(fn ($items) => $items->take(5)->values()->map(fn (array $item, int $index) => [
                'ruta' => $this->images->store($item['archivo'], $storeId),
                'talla' => ($value = trim((string) ($item['talla'] ?? ''))) === '' ? null : $value,
                'color' => ($value = trim((string) ($item['color'] ?? ''))) === '' ? null : $value,
                'orden' => $index,
                'principal' => $index === 0,
            ]))->values()->all();
        $imagePath = $storedGallery[0]['ruta'] ?? ($image ? $this->images->store($image, $storeId) : null);

        try {
            return DB::transaction(function () use ($data, $user, $storeId, $imagePath, $storedGallery): Producto {
                $store = Tienda::query()->with('plan')->lockForUpdate()->findOrFail($storeId);
                if ((bool) ($data['activo'] ?? false)) {
                    $this->planGuard->ensureActiveSlot($store);
                }

                $variants = Arr::pull($data, 'variantes', []);
                $data['tienda_id'] = $storeId;
                $data['codigo'] = $this->codeGenerator->generate();
                $data['imagen'] = $imagePath;

                $product = Producto::create($data);
                if ($storedGallery !== []) {
                    $product->imagenes()->createMany(array_map(fn (array $item) => $item + ['tienda_id' => $storeId], $storedGallery));
                }
                $createdVariants = $product->variantes()->createMany(
                    collect($variants)
                        ->map(fn (array $variant): array => $variant + ['tienda_id' => $storeId])
                        ->all()
                );
                foreach ($createdVariants as $variant) {
                    $this->stockMovements->recordInitialStock($variant, $user, $product);
                }
                $this->audit->record($product, 'producto.creado', 'Producto creado', $user, [
                    'codigo' => $product->codigo,
                    'activo' => $product->activo,
                    'variantes' => count($variants),
                ]);

                return $product->load('variantes');
            }, 3);
        } catch (Throwable $exception) {
            $this->images->delete($imagePath);
            foreach ($storedGallery as $item) {
                if ($item['ruta'] !== $imagePath) {
                    $this->images->delete($item['ruta']);
                }
            }

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        Producto $product,
        array $data,
        User $user,
        ?UploadedFile $image = null,
        array $gallery = [],
        array $existingImages = []
    ): Producto {
        $variants = Arr::pull($data, 'variantes', []);
        $existingVariantUpdates = Arr::pull($data, 'variantes_existentes', []);
        $newImagePath = $image ? $this->images->store($image, (int) $product->tienda_id) : null;
        $storedGallery = collect($gallery)->map(fn (array $item): array => [
            'ruta' => $this->images->store($item['archivo'], (int) $product->tienda_id),
            'talla' => ($value = trim((string) ($item['talla'] ?? ''))) === '' ? null : $value,
            'color' => ($value = trim((string) ($item['color'] ?? ''))) === '' ? null : $value,
        ])->all();
        $storedReplacements = collect($existingImages)
            ->filter(fn (array $item) => ! ($item['eliminar'] ?? false) && ($item['archivo'] ?? null) instanceof UploadedFile)
            ->mapWithKeys(fn (array $item) => [(int) $item['id'] => $this->images->store($item['archivo'], (int) $product->tienda_id)])
            ->all();

        if ($newImagePath !== null) {
            $data['imagen'] = $newImagePath;
        } elseif (! $product->imagen && $storedGallery !== []) {
            $data['imagen'] = $storedGallery[0]['ruta'];
        }

        try {
            [$updatedProduct, $previousImagePath, $obsoleteGalleryPaths] = DB::transaction(function () use ($product, $data, $variants, $existingVariantUpdates, $storedGallery, $storedReplacements, $existingImages, $newImagePath, $user) {
                $store = Tienda::query()->with('plan')->lockForUpdate()->findOrFail($product->tienda_id);
                $lockedProduct = Producto::query()->lockForUpdate()->findOrFail($product->id);

                if (! $lockedProduct->activo && (bool) ($data['activo'] ?? false)) {
                    $this->planGuard->ensureActiveSlot($store);
                }

                $previousImagePath = $lockedProduct->imagen;
                $obsoleteGalleryPaths = [];
                $changes = $this->meaningfulChanges($lockedProduct, $data);
                if ($changes !== []) {
                    $lockedProduct->update($data);
                    $this->audit->record(
                        $lockedProduct,
                        $this->updateAction($changes),
                        $this->updateDescription($changes),
                        $user,
                        ['cambios' => $changes]
                    );
                }

                $variantChanges = [];
                if ($existingVariantUpdates !== []) {
                    $updatesById = collect($existingVariantUpdates)->keyBy('id');
                    $lockedVariants = $lockedProduct->variantes()->withoutGlobalScopes()
                        ->where('tienda_id', $lockedProduct->tienda_id)
                        ->whereIn('id', $updatesById->keys())
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                    if ($lockedVariants->count() !== $updatesById->count()) {
                        throw ValidationException::withMessages(['variantes_existentes' => 'Una variante no pertenece a este producto.']);
                    }
                    foreach ($lockedVariants as $variant) {
                        $requestedUpdate = $updatesById->get($variant->id);
                        $update = collect($requestedUpdate)->only(['talla', 'detalle_talla', 'color', 'codigo_barra', 'precio_compra', 'precio_venta', 'precio_oferta', 'stock_minimo'])->all();
                        $requestedStock = (int) ($requestedUpdate['stock'] ?? $variant->stock);
                        $oldSize = $variant->talla;
                        $oldColor = $variant->color;
                        $candidate = clone $variant;
                        $candidate->fill($update);
                        $dirty = $candidate->getDirty();
                        if ($dirty !== []) {
                            $variant->update($update);
                            if (isset($dirty['talla']) || isset($dirty['color'])) {
                                $lockedProduct->imagenes()->withoutGlobalScopes()
                                    ->where('tienda_id', $lockedProduct->tienda_id)
                                    ->where('talla', $oldSize)
                                    ->where('color', $oldColor)
                                    ->update(['talla' => $variant->talla, 'color' => $variant->color]);
                            }
                        }
                        if ($requestedStock !== (int) $variant->stock) {
                            $previousStock = (int) $variant->stock;
                            $this->stockMovements->setStock(
                                $variant,
                                $requestedStock,
                                StockMovimiento::TIPO_AJUSTE,
                                $user,
                                $lockedProduct,
                                $requestedUpdate['motivo_stock']
                            );
                            $dirty['stock'] = ['anterior' => $previousStock, 'nuevo' => $requestedStock];
                        }
                        if ($dirty !== []) {
                            $variantChanges[] = ['id' => $variant->id, 'cambios' => $dirty];
                        }
                    }
                    if ($variantChanges !== []) {
                        $this->audit->record($lockedProduct, 'producto.variantes_actualizadas', 'Variantes existentes actualizadas', $user, ['variantes' => $variantChanges]);
                    }
                }

                $createdVariants = $lockedProduct->variantes()->createMany(
                    collect($variants)
                        ->map(fn (array $variant): array => $variant + ['tienda_id' => (int) $lockedProduct->tienda_id])
                        ->all()
                );
                foreach ($createdVariants as $variant) {
                    $this->stockMovements->recordInitialStock($variant, $user, $lockedProduct);
                }
                if ($createdVariants->isNotEmpty()) {
                    $this->audit->record(
                        $lockedProduct,
                        'producto.variantes_agregadas',
                        'Nuevas variantes agregadas al producto',
                        $user,
                        ['variantes' => $createdVariants->map->only(['id', 'talla', 'color', 'stock', 'stock_minimo'])->all()]
                    );
                }

                $requestedImageEdits = collect($existingImages)->keyBy(fn (array $item) => (int) ($item['id'] ?? 0));
                if ($requestedImageEdits->isNotEmpty()) {
                    $lockedImages = $lockedProduct->imagenes()->withoutGlobalScopes()
                        ->where('tienda_id', $lockedProduct->tienda_id)
                        ->whereIn('id', $requestedImageEdits->keys())
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();
                    if ($lockedImages->count() !== $requestedImageEdits->count()) {
                        throw ValidationException::withMessages(['imagenes_existentes' => 'Una imagen no pertenece a este producto.']);
                    }
                    $imageChanges = [];
                    foreach ($lockedImages as $existingImage) {
                        $edit = $requestedImageEdits->get($existingImage->id);
                        if ($edit['eliminar'] ?? false) {
                            $obsoleteGalleryPaths[] = $existingImage->ruta;
                            $imageChanges[] = ['id' => $existingImage->id, 'accion' => 'eliminada'];
                            $existingImage->delete();
                        } elseif (isset($storedReplacements[$existingImage->id])) {
                            $obsoleteGalleryPaths[] = $existingImage->ruta;
                            $existingImage->update(['ruta' => $storedReplacements[$existingImage->id]]);
                            $imageChanges[] = ['id' => $existingImage->id, 'accion' => 'reemplazada'];
                        }
                    }
                    if ($imageChanges !== []) {
                        $this->audit->record($lockedProduct, 'producto.imagenes_actualizadas', 'Imágenes existentes actualizadas', $user, ['imagenes' => $imageChanges]);
                    }
                }

                $addedImages = collect($storedGallery)
                    ->groupBy(fn (array $item) => mb_strtolower(($item['talla'] ?? '').'|'.($item['color'] ?? '')))
                    ->flatMap(function ($items) use ($lockedProduct): array {
                        $first = $items->first();
                        $existing = $lockedProduct->imagenes()->withoutGlobalScopes()
                            ->where('tienda_id', $lockedProduct->tienda_id)
                            ->where('talla', $first['talla'])
                            ->where('color', $first['color'])
                            ->lockForUpdate()
                            ->get();
                        if ($existing->count() + $items->count() > 5) {
                            throw ValidationException::withMessages(['imagenes' => 'Cada combinación de talla y color admite máximo cinco imágenes.']);
                        }
                        $nextOrder = $existing->isEmpty() ? 0 : ((int) $existing->max('orden')) + 1;

                        return $items->values()->map(fn (array $item, int $offset) => $lockedProduct->imagenes()->create($item + [
                            'tienda_id' => (int) $lockedProduct->tienda_id,
                            'orden' => $nextOrder + $offset,
                            'principal' => $existing->isEmpty() && $offset === 0,
                        ]))->all();
                    });
                if ($addedImages->isNotEmpty()) {
                    $this->audit->record($lockedProduct, 'producto.imagenes_agregadas', 'Nuevas imágenes agregadas al producto', $user, [
                        'imagenes' => $addedImages->map->only(['id', 'talla', 'color', 'orden', 'principal'])->all(),
                    ]);
                }

                if ($newImagePath === null && ($previousImagePath === null || in_array($previousImagePath, $obsoleteGalleryPaths, true))) {
                    $fallbackPath = $lockedProduct->imagenes()->withoutGlobalScopes()
                        ->where('tienda_id', $lockedProduct->tienda_id)
                        ->orderByDesc('principal')->orderBy('orden')->orderBy('id')
                        ->value('ruta');
                    $lockedProduct->update(['imagen' => $fallbackPath]);
                }

                return [$lockedProduct, $previousImagePath, $obsoleteGalleryPaths];
            }, 3);
        } catch (Throwable $exception) {
            $this->images->delete($newImagePath);
            foreach ($storedGallery as $item) {
                $this->images->delete($item['ruta']);
            }
            foreach ($storedReplacements as $path) {
                $this->images->delete($path);
            }

            throw $exception;
        }

        if ($newImagePath !== null && $previousImagePath !== $newImagePath) {
            $this->images->delete($previousImagePath);
        }
        foreach (array_unique($obsoleteGalleryPaths) as $path) {
            if ($path !== $updatedProduct->imagen) {
                $this->images->delete($path);
            }
        }

        return $updatedProduct->refresh()->load('variantes', 'imagenes');
    }

    public function deactivate(Producto $product, User $user): Producto
    {
        if (! $product->activo) {
            return $product;
        }

        return $this->update($product, ['activo' => false], $user);
    }

    /** @param array<string, mixed> $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function meaningfulChanges(Producto $product, array $data): array
    {
        $candidate = clone $product;
        $candidate->fill($data);

        return collect($candidate->getDirty())
            ->except('updated_at')
            ->mapWithKeys(fn ($value, $field) => [$field => [
                'anterior' => $candidate->getRawOriginal($field),
                'nuevo' => $value,
            ]])
            ->all();
    }

    /** @param array<string, mixed> $changes */
    private function updateAction(array $changes): string
    {
        if (isset($changes['activo'])) {
            return (bool) $changes['activo']['nuevo'] ? 'producto.activado' : 'producto.desactivado';
        }

        return 'producto.actualizado';
    }

    /** @param array<string, mixed> $changes */
    private function updateDescription(array $changes): string
    {
        return match ($this->updateAction($changes)) {
            'producto.activado' => 'Producto activado',
            'producto.desactivado' => 'Producto desactivado',
            default => 'Producto actualizado',
        };
    }
}
