<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryManager
{
    public function __construct(
        private readonly CategoryNameNormalizer $normalizer,
        private readonly AuditLogger $audit
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $user): Categoria
    {
        return DB::transaction(function () use ($data, $user): Categoria {
            $storeId = (int) $user->tienda_id;
            Tienda::query()->lockForUpdate()->findOrFail($storeId);

            $data['tienda_id'] = $storeId;
            $data['nombre_normalizado'] = $this->normalizer->normalize($data['nombre']);
            $this->ensureUniqueName($data['nombre_normalizado']);
            $data['slug'] = $this->uniqueSlug($data['nombre']);

            $category = Categoria::create($data);
            $this->audit->record($category, 'categoria.creada', 'Categoría creada', $user, [
                'nombre' => $category->nombre,
                'slug' => $category->slug,
                'genero' => $category->genero,
                'activo' => $category->activo,
            ]);

            return $category;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Categoria $category, array $data, User $user): Categoria
    {
        return DB::transaction(function () use ($category, $data, $user): Categoria {
            Tienda::query()->lockForUpdate()->findOrFail($category->tienda_id);
            $lockedCategory = Categoria::query()->lockForUpdate()->findOrFail($category->id);

            if ($lockedCategory->activo && ! (bool) $data['activo']) {
                $this->ensureCanDeactivate($lockedCategory);
            }

            $data['nombre_normalizado'] = $this->normalizer->normalize($data['nombre']);
            $this->ensureUniqueName($data['nombre_normalizado'], $lockedCategory->id);
            $data['slug'] = $this->uniqueSlug($data['nombre'], $lockedCategory->id);
            $changes = $this->meaningfulChanges($lockedCategory, $data);

            if ($changes !== []) {
                $lockedCategory->update($data);
                $this->audit->record(
                    $lockedCategory,
                    $this->updateAction($changes),
                    $this->updateDescription($changes),
                    $user,
                    ['cambios' => $changes]
                );
            }

            return $lockedCategory->refresh();
        }, 3);
    }

    public function delete(Categoria $category, User $user): void
    {
        DB::transaction(function () use ($category, $user): void {
            Tienda::query()->lockForUpdate()->findOrFail($category->tienda_id);
            $lockedCategory = Categoria::query()->lockForUpdate()->findOrFail($category->id);

            if ($lockedCategory->productos()->exists() || $lockedCategory->subcategorias()->exists()) {
                throw ValidationException::withMessages([
                    'categoria' => 'No puedes eliminar una categoría con productos o subcategorías asociadas.',
                ]);
            }

            $this->audit->record($lockedCategory, 'categoria.eliminada', 'Categoría eliminada', $user, [
                'nombre' => $lockedCategory->nombre,
                'slug' => $lockedCategory->slug,
                'genero' => $lockedCategory->genero,
                'activo' => $lockedCategory->activo,
            ]);
            $lockedCategory->delete();
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, array{anterior: mixed, nuevo: mixed}>
     */
    private function meaningfulChanges(Categoria $category, array $data): array
    {
        $candidate = clone $category;
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
            return (bool) $changes['activo']['nuevo'] ? 'categoria.activada' : 'categoria.desactivada';
        }

        return 'categoria.actualizada';
    }

    /** @param array<string, array{anterior: mixed, nuevo: mixed}> $changes */
    private function updateDescription(array $changes): string
    {
        return match ($this->updateAction($changes)) {
            'categoria.activada' => 'Categoría activada',
            'categoria.desactivada' => 'Categoría desactivada',
            default => 'Categoría actualizada',
        };
    }

    private function ensureCanDeactivate(Categoria $category): void
    {
        $hasActiveProducts = $category->productos()->where('activo', true)->exists();
        $hasActiveSubcategories = $category->subcategorias()->where('activo', true)->exists();

        if ($hasActiveProducts || $hasActiveSubcategories) {
            throw ValidationException::withMessages([
                'activo' => 'Desactiva o mueve primero los productos y subcategorías activas.',
            ]);
        }
    }

    private function ensureUniqueName(string $normalizedName, ?int $ignoreId = null): void
    {
        $exists = Categoria::query()
            ->where('nombre_normalizado', $normalizedName)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'nombre' => 'Ya existe una categoría con este nombre en la tienda.',
            ]);
        }
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'categoria';
        $slug = $base;
        $suffix = 2;

        while (Categoria::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
