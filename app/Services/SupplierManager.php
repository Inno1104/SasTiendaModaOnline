<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Proveedor
    {
        return DB::transaction(function () use ($data, $user): Proveedor {
            $storeId = (int) $user->tienda_id;
            Tienda::query()->lockForUpdate()->findOrFail($storeId);
            $this->ensureUniqueTaxId($data['ruc'] ?? null);

            $supplier = Proveedor::create([
                ...$data,
                'tienda_id' => $storeId,
                'activo' => true,
            ]);

            $this->audit->record($supplier, 'proveedor.creado', 'Proveedor creado', $user, [
                'nombre' => $supplier->nombre,
                'ruc' => $supplier->ruc,
                'email' => $supplier->email,
                'telefono' => $supplier->telefono,
                'activo' => $supplier->activo,
            ]);

            return $supplier;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Proveedor $supplier, array $data, User $user): Proveedor
    {
        return DB::transaction(function () use ($supplier, $data, $user): Proveedor {
            $storeId = (int) $user->tienda_id;
            Tienda::query()->lockForUpdate()->findOrFail($storeId);
            $lockedSupplier = Proveedor::query()->lockForUpdate()->findOrFail($supplier->id);
            $this->ensureUniqueTaxId($data['ruc'] ?? null, $lockedSupplier->id);

            $changes = $this->meaningfulChanges($lockedSupplier, $data);
            if ($changes !== []) {
                $lockedSupplier->fill($data);
                $lockedSupplier->save();
                $this->audit->record(
                    $lockedSupplier,
                    'proveedor.actualizado',
                    'Proveedor actualizado',
                    $user,
                    ['cambios' => $changes]
                );
            }

            return $lockedSupplier->refresh();
        }, 3);
    }

    public function changeStatus(Proveedor $supplier, bool $active, User $user): bool
    {
        return DB::transaction(function () use ($supplier, $active, $user): bool {
            $storeId = (int) $user->tienda_id;
            Tienda::query()->lockForUpdate()->findOrFail($storeId);
            $lockedSupplier = Proveedor::query()->lockForUpdate()->findOrFail($supplier->id);

            if ($lockedSupplier->activo === $active) {
                return false;
            }

            $previousState = $lockedSupplier->activo;
            $lockedSupplier->update(['activo' => $active]);
            $this->audit->record(
                $lockedSupplier,
                $active ? 'proveedor.activado' : 'proveedor.desactivado',
                $active ? 'Proveedor activado' : 'Proveedor desactivado',
                $user,
                [
                    'cambios' => [
                        'activo' => ['anterior' => $previousState, 'nuevo' => $active],
                    ],
                    'dependencias' => [
                        'productos' => $lockedSupplier->productos()->count(),
                        'compras' => $lockedSupplier->compras()->count(),
                    ],
                ]
            );

            return true;
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function meaningfulChanges(Proveedor $supplier, array $data): array
    {
        $candidate = clone $supplier;
        $candidate->fill($data);

        return collect($candidate->getDirty())
            ->except(['updated_at', 'tienda_id', 'activo'])
            ->mapWithKeys(fn ($value, $field) => [$field => [
                'anterior' => $candidate->getRawOriginal($field),
                'nuevo' => $value,
            ]])
            ->all();
    }

    private function ensureUniqueTaxId(?string $taxId, ?int $ignoreId = null): void
    {
        if ($taxId === null) {
            return;
        }

        $exists = Proveedor::query()
            ->where('ruc', $taxId)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'ruc' => 'Ya existe un proveedor con esta identificación fiscal en la tienda.',
            ]);
        }
    }
}
