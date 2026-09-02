<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
    public function create(array $data, User $user, ?UploadedFile $image = null): Producto
    {
        $storeId = (int) $user->tienda_id;
        $imagePath = $image ? $this->images->store($image, $storeId) : null;

        try {
            return DB::transaction(function () use ($data, $user, $storeId, $imagePath): Producto {
                $store = Tienda::query()->with('plan')->lockForUpdate()->findOrFail($storeId);
                if ((bool) ($data['activo'] ?? false)) {
                    $this->planGuard->ensureActiveSlot($store);
                }

                $variants = Arr::pull($data, 'variantes', []);
                $data['tienda_id'] = $storeId;
                $data['codigo'] = $this->codeGenerator->generate();
                $data['imagen'] = $imagePath;

                $product = Producto::create($data);
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

            throw $exception;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        Producto $product,
        array $data,
        User $user,
        ?UploadedFile $image = null
    ): Producto {
        unset($data['variantes']);
        $newImagePath = $image ? $this->images->store($image, (int) $product->tienda_id) : null;

        if ($newImagePath !== null) {
            $data['imagen'] = $newImagePath;
        }

        try {
            [$updatedProduct, $previousImagePath] = DB::transaction(function () use ($product, $data, $user) {
                $store = Tienda::query()->with('plan')->lockForUpdate()->findOrFail($product->tienda_id);
                $lockedProduct = Producto::query()->lockForUpdate()->findOrFail($product->id);

                if (! $lockedProduct->activo && (bool) ($data['activo'] ?? false)) {
                    $this->planGuard->ensureActiveSlot($store);
                }

                $previousImagePath = $lockedProduct->imagen;
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

                return [$lockedProduct, $previousImagePath];
            }, 3);
        } catch (Throwable $exception) {
            $this->images->delete($newImagePath);

            throw $exception;
        }

        if ($newImagePath !== null && $previousImagePath !== $newImagePath) {
            $this->images->delete($previousImagePath);
        }

        return $updatedProduct->refresh();
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
