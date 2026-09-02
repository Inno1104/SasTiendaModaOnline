<?php

namespace App\Http\Requests;

use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProveedorDataRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function supplierRules(?Proveedor $supplier = null): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'nombre' => ['required', 'string', 'max:200'],
            'ruc' => [
                'nullable',
                'string',
                'between:5,30',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('proveedores', 'ruc')
                    ->where('tienda_id', $storeId)
                    ->ignore($supplier?->id),
            ],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:21', 'regex:/^\+?[0-9]{6,20}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'pais' => ['nullable', 'string', 'max:100'],
            'contacto' => ['nullable', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nombre', 'direccion', 'ciudad', 'pais', 'contacto'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', trim($value));
            $normalized[$field] = $value === '' ? null : $value;
        }

        if ($this->exists('ruc')) {
            $ruc = mb_strtoupper((string) preg_replace('/[^\pL\pN]/u', '', (string) $this->input('ruc')));
            $normalized['ruc'] = $ruc === '' ? null : $ruc;
        }

        if ($this->exists('email')) {
            $email = mb_strtolower(trim((string) $this->input('email')));
            $normalized['email'] = $email === '' ? null : $email;
        }

        if ($this->exists('telefono')) {
            $rawPhone = trim((string) $this->input('telefono'));
            $hasInternationalPrefix = str_starts_with($rawPhone, '+');
            $digits = preg_replace('/\D+/', '', $rawPhone);
            $phone = ($hasInternationalPrefix ? '+' : '').$digits;
            $normalized['telefono'] = $digits === '' ? null : $phone;
        }

        $this->merge($normalized);
    }
}
