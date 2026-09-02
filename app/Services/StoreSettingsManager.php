<?php

namespace App\Services;

use App\Models\Tienda;
use App\Models\User;
use App\Support\StoreCurrency;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StoreSettingsManager
{
    private const SENSITIVE_FIELDS = ['moneda', 'igv', 'serie_boleta', 'serie_factura', 'serie_ticket'];

    private const FISCAL_FIELDS = ['moneda', 'simbolo_moneda', 'igv', 'precios_incluyen_igv'];

    private const SERIES_FIELDS = ['serie_boleta', 'serie_factura', 'serie_ticket'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function update(Tienda $store, array $data, User $user): bool
    {
        $expectedVersion = (int) Arr::pull($data, 'configuracion_version');
        $reason = (string) Arr::pull($data, 'motivo_cambio', '');
        Arr::forget($data, 'confirmar_cambio');
        $data['simbolo_moneda'] = StoreCurrency::symbol($data['moneda']);

        return DB::transaction(function () use ($store, $data, $user, $reason, $expectedVersion): bool {
            /** @var Tienda $lockedStore */
            $lockedStore = Tienda::query()->lockForUpdate()->findOrFail($store->id);
            $candidate = clone $lockedStore;
            $candidate->fill($data);
            $changes = collect($candidate->getDirty())
                ->except(['updated_at'])
                ->mapWithKeys(fn ($value, $field) => [$field => [
                    'anterior' => $candidate->getRawOriginal($field),
                    'nuevo' => $value,
                ]])
                ->all();

            if ($changes === []) {
                return false;
            }
            if (array_intersect(self::SENSITIVE_FIELDS, array_keys($changes)) !== [] && mb_strlen($reason) < 10) {
                throw ValidationException::withMessages([
                    'motivo_cambio' => 'Explica el motivo del cambio fiscal con al menos 10 caracteres.',
                ]);
            }
            if ((int) $lockedStore->configuracion_version !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'configuracion_version' => 'La configuración cambió mientras editabas. Recarga la página y revisa los valores actuales.',
                ]);
            }

            $lockedStore->fill($data);
            $lockedStore->configuracion_version = $expectedVersion + 1;
            $lockedStore->save();
            $this->auditChanges($lockedStore, $user, $changes, $reason);
            $store->setRawAttributes($lockedStore->getAttributes(), true);

            return true;
        }, 3);
    }

    /** @param array<string, array{anterior:mixed,nuevo:mixed}> $changes */
    private function auditChanges(Tienda $store, User $user, array $changes, string $reason): void
    {
        $fiscalChanges = Arr::only($changes, self::FISCAL_FIELDS);
        if ($fiscalChanges !== []) {
            $this->audit->record($store, 'tienda.configuracion_fiscal_actualizada', 'Configuración fiscal y monetaria actualizada', $user, [
                'motivo' => $reason !== '' ? $reason : null,
                'cambios' => $fiscalChanges,
            ]);
        }

        $seriesChanges = Arr::only($changes, self::SERIES_FIELDS);
        if ($seriesChanges !== []) {
            $this->audit->record($store, 'tienda.series_actualizadas', 'Series de comprobantes actualizadas', $user, [
                'motivo' => $reason,
                'cambios' => $seriesChanges,
            ]);
        }
    }
}
