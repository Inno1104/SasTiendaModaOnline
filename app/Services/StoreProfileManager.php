<?php

namespace App\Services;

use App\Models\Tienda;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StoreProfileManager
{
    private const IDENTITY_FIELDS = ['nombre', 'ruc', 'email', 'telefono', 'whatsapp', 'direccion', 'sitio_web', 'redes_sociales'];

    private const LOCATION_FIELDS = ['pais_codigo', 'provincia', 'ciudad'];

    public function __construct(
        private readonly StoreLogoStorage $logos,
        private readonly AuditLogger $audit
    ) {}

    /** @param array<string, mixed> $data */
    public function update(Tienda $store, array $data, User $user): bool
    {
        $logo = Arr::pull($data, 'logo');
        $removeLogo = (bool) Arr::pull($data, 'eliminar_logo', false);
        $newLogoPath = $logo instanceof UploadedFile ? $this->logos->store($logo, (int) $store->id) : null;
        $oldLogoPath = $store->logo;

        try {
            [$changed, $oldLogoPath] = DB::transaction(function () use ($store, $data, $newLogoPath, $removeLogo, $user): array {
                /** @var Tienda $lockedStore */
                $lockedStore = Tienda::query()->lockForUpdate()->findOrFail($store->id);
                $replacedLogoPath = $lockedStore->logo;
                $lockedStore->fill($data);
                if ($newLogoPath !== null) {
                    $lockedStore->logo = $newLogoPath;
                } elseif ($removeLogo) {
                    $lockedStore->logo = null;
                }

                if (! $lockedStore->isDirty()) {
                    return [false, $replacedLogoPath];
                }

                $changes = collect($lockedStore->getDirty())
                    ->except(['updated_at'])
                    ->mapWithKeys(fn ($value, $field) => [$field => [
                        'anterior' => $lockedStore->getRawOriginal($field),
                        'nuevo' => $value,
                    ]])
                    ->all();
                $lockedStore->save();
                $this->auditChanges($lockedStore, $user, $changes);
                $store->setRawAttributes($lockedStore->getAttributes(), true);

                return [true, $replacedLogoPath];
            }, 3);
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                $this->logos->deleteNew($newLogoPath, (int) $store->id);
            }
            throw $exception;
        }

        if ($changed && $oldLogoPath !== null && ($newLogoPath !== null || $removeLogo)) {
            $this->logos->deleteCurrentIfUnreferenced($oldLogoPath, (int) $store->id);
        }

        return $changed;
    }

    /** @param array<string, array{anterior:mixed,nuevo:mixed}> $changes */
    private function auditChanges(Tienda $store, User $user, array $changes): void
    {
        $identityChanges = Arr::only($changes, self::IDENTITY_FIELDS);
        if ($identityChanges !== []) {
            $this->audit->record($store, 'tienda.identidad_actualizada', 'Identidad comercial de la tienda actualizada', $user, [
                'cambios' => $identityChanges,
            ]);
        }

        $locationChanges = Arr::only($changes, self::LOCATION_FIELDS);
        if ($locationChanges !== []) {
            $this->audit->record($store, 'tienda.ubicacion_actualizada', 'Ubicación de la tienda actualizada', $user, [
                'cambios' => $locationChanges,
            ]);
        }

        if (isset($changes['logo'])) {
            $removed = $changes['logo']['nuevo'] === null;
            $this->audit->record(
                $store,
                $removed ? 'tienda.logo_eliminado' : 'tienda.logo_actualizado',
                $removed ? 'Logo de la tienda eliminado' : 'Logo de la tienda actualizado',
                $user,
                ['cambios' => ['logo' => [
                    'anterior' => $changes['logo']['anterior'] !== null,
                    'nuevo' => $changes['logo']['nuevo'] !== null,
                ]]]
            );
        }
    }
}
