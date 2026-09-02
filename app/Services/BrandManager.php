<?php

namespace App\Services;

use App\Models\Marca;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandManager
{
    public function __construct(
        private readonly BrandNameNormalizer $normalizer,
        private readonly AuditLogger $audit
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Marca
    {
        return DB::transaction(function () use ($data, $user): Marca {
            $storeId = (int) $user->tienda_id;
            Tienda::query()->lockForUpdate()->findOrFail($storeId);

            $data = $this->prepareIdentity($data);
            $data['tienda_id'] = $storeId;
            $this->ensureUniqueName($data['nombre_normalizado']);

            $brand = Marca::create($data);
            $this->audit->record($brand, 'marca.creada', 'Marca creada', $user, [
                'nombre' => $brand->nombre,
                'activo' => $brand->activo,
            ]);

            return $brand;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Marca $brand, array $data, User $user): Marca
    {
        return DB::transaction(function () use ($brand, $data, $user): Marca {
            Tienda::query()->lockForUpdate()->findOrFail($brand->tienda_id);
            $lockedBrand = Marca::query()->lockForUpdate()->findOrFail($brand->id);
            $data = $this->prepareIdentity($data);
            $this->ensureUniqueName($data['nombre_normalizado'], $lockedBrand->id);
            $changes = $this->meaningfulChanges($lockedBrand, $data);

            if ($changes !== []) {
                $lockedBrand->update($data);
                $action = $this->updateAction($changes);
                $this->audit->record(
                    $lockedBrand,
                    $action,
                    $this->updateDescription($action),
                    $user,
                    ['cambios' => $changes]
                );
            }

            return $lockedBrand->refresh();
        }, 3);
    }

    public function delete(Marca $brand, User $user): void
    {
        DB::transaction(function () use ($brand, $user): void {
            Tienda::query()->lockForUpdate()->findOrFail($brand->tienda_id);
            $lockedBrand = Marca::query()->lockForUpdate()->findOrFail($brand->id);

            if ($lockedBrand->productos()->exists()) {
                throw ValidationException::withMessages([
                    'marca' => 'No puedes eliminar una marca con productos asociados. Desactívala si ya no deseas utilizarla.',
                ]);
            }

            $this->audit->record($lockedBrand, 'marca.eliminada', 'Marca eliminada', $user, [
                'nombre' => $lockedBrand->nombre,
                'activo' => $lockedBrand->activo,
            ]);
            $lockedBrand->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function meaningfulChanges(Marca $brand, array $data): array
    {
        $candidate = clone $brand;
        $candidate->fill($data);

        return collect($candidate->getDirty())
            ->except(['updated_at', 'nombre_normalizado'])
            ->mapWithKeys(fn ($value, $field) => [$field => [
                'anterior' => $candidate->getRawOriginal($field),
                'nuevo' => $value,
            ]])
            ->all();
    }

    /** @param array<string, array{anterior: mixed, nuevo: mixed}> $changes */
    private function updateAction(array $changes): string
    {
        if (isset($changes['activo'])) {
            return (bool) $changes['activo']['nuevo'] ? 'marca.activada' : 'marca.desactivada';
        }

        return 'marca.actualizada';
    }

    private function updateDescription(string $action): string
    {
        return match ($action) {
            'marca.activada' => 'Marca activada',
            'marca.desactivada' => 'Marca desactivada',
            default => 'Marca actualizada',
        };
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareIdentity(array $data): array
    {
        $data['nombre'] = Str::squish($data['nombre']);
        $data['nombre_normalizado'] = $this->normalizer->normalize($data['nombre']);

        return $data;
    }

    private function ensureUniqueName(string $normalizedName, ?int $ignoreId = null): void
    {
        $exists = Marca::query()
            ->where('nombre_normalizado', $normalizedName)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una marca con este nombre en la tienda.',
            ]);
        }
    }
}
