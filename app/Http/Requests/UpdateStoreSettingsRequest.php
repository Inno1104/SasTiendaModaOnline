<?php

namespace App\Http\Requests;

use App\Models\Tienda;
use App\Support\StoreCurrency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStoreSettingsRequest extends FormRequest
{
    private const SENSITIVE_FIELDS = ['moneda', 'igv', 'serie_boleta', 'serie_factura', 'serie_ticket'];

    public function authorize(): bool
    {
        $store = $this->user()?->tienda;

        return $store instanceof Tienda && ($this->user()?->can('update', $store) ?? false);
    }

    public function rules(): array
    {
        return [
            'igv' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'precios_incluyen_igv' => ['required', 'boolean'],
            'moneda' => ['required', 'string', Rule::in(array_keys(StoreCurrency::ALLOWED))],
            'serie_boleta' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'serie_factura' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'serie_ticket' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9-]+$/'],
            'configuracion_version' => ['required', 'integer', 'min:0'],
            'motivo_cambio' => ['nullable', 'string', 'max:500'],
            'confirmar_cambio' => ['nullable', 'boolean'],
            'transferencia_activa' => ['required', 'boolean'],
            'transferencia_banco' => ['nullable', Rule::requiredIf(fn () => $this->boolean('transferencia_activa')), 'string', 'max:100'],
            'transferencia_tipo_cuenta' => ['nullable', Rule::requiredIf(fn () => $this->boolean('transferencia_activa')), Rule::in(['ahorros', 'corriente'])],
            'transferencia_numero_cuenta' => ['nullable', Rule::requiredIf(fn () => $this->boolean('transferencia_activa')), 'string', 'max:60'],
            'transferencia_titular' => ['nullable', Rule::requiredIf(fn () => $this->boolean('transferencia_activa')), 'string', 'max:150'],
            'transferencia_identificacion' => ['nullable', 'string', 'max:30'],
            'transferencia_instrucciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->hasSensitiveChanges()) {
                return;
            }
            if (mb_strlen((string) $this->input('motivo_cambio')) < 10) {
                $validator->errors()->add('motivo_cambio', 'Explica el motivo del cambio fiscal con al menos 10 caracteres.');
            }
            if (! $this->boolean('confirmar_cambio')) {
                $validator->errors()->add('confirmar_cambio', 'Debes confirmar el cambio fiscal antes de guardarlo.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'moneda' => mb_strtoupper(trim((string) $this->input('moneda'))),
            'serie_boleta' => mb_strtoupper(trim((string) $this->input('serie_boleta'))),
            'serie_factura' => mb_strtoupper(trim((string) $this->input('serie_factura'))),
            'serie_ticket' => mb_strtoupper(trim((string) $this->input('serie_ticket'))),
            'motivo_cambio' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('motivo_cambio'))),
            'precios_incluyen_igv' => $this->boolean('precios_incluyen_igv'),
            'confirmar_cambio' => $this->boolean('confirmar_cambio'),
            'transferencia_activa' => $this->boolean('transferencia_activa'),
        ]);
    }

    private function hasSensitiveChanges(): bool
    {
        $store = $this->user()?->tienda;
        if (! $store instanceof Tienda) {
            return false;
        }

        foreach (self::SENSITIVE_FIELDS as $field) {
            $incoming = $field === 'igv'
                ? number_format((float) $this->input($field), 2, '.', '')
                : (string) $this->input($field);
            $current = $field === 'igv'
                ? number_format((float) $store->{$field}, 2, '.', '')
                : (string) $store->{$field};
            if ($incoming !== $current) {
                return true;
            }
        }

        return false;
    }
}
