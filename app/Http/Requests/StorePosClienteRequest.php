<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienda_id !== null && ! $this->user()->es_super_admin;
    }

    public function rules(): array
    {
        $tiendaId = (int) $this->user()->tienda_id;

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'dni' => ['nullable', 'string', 'max:30', Rule::unique('clientes', 'dni')->where('tienda_id', $tiendaId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('clientes', 'email')->where('tienda_id', $tiendaId)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['nombre', 'apellido', 'dni', 'telefono', 'email'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        if (isset($normalized['email'])) {
            $normalized['email'] = mb_strtolower($normalized['email']);
        }

        $this->merge($normalized);
    }
}
