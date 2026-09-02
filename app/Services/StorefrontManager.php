<?php

namespace App\Services;

use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class StorefrontManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function update(Tienda $store, array $data, User $user): bool
    {
        return DB::transaction(function () use ($store, $data, $user): bool {
            $lockedStore = Tienda::query()->lockForUpdate()->findOrFail($store->id);
            $lockedStore->fill($data);

            if (! $lockedStore->isDirty()) {
                return false;
            }

            $changes = collect($lockedStore->getDirty())
                ->except('updated_at')
                ->mapWithKeys(fn ($value, $field) => [$field => [
                    'anterior' => $lockedStore->getRawOriginal($field),
                    'nuevo' => $value,
                ]])->all();

            $lockedStore->save();
            $this->audit->record(
                $lockedStore,
                'tienda.publicacion_actualizada',
                'Configuración de la tienda pública actualizada',
                $user,
                ['cambios' => $changes]
            );
            $store->setRawAttributes($lockedStore->getAttributes(), true);

            return true;
        }, 3);
    }
}
