<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cliente::class) ?? false;
    }

    public function rules(): array
    {
        return $this->customerRules();
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedInput());
    }

    /** @return array<string, mixed> */
    protected function customerRules(?Cliente $cliente = null): array
    {
        $tiendaId = (int) $this->user()->tienda_id;

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'dni' => ['nullable', 'string', 'max:30', Rule::unique('clientes', 'dni')->where('tienda_id', $tiendaId)->ignore($cliente)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('clientes', 'email')->where('tienda_id', $tiendaId)->ignore($cliente)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'genero' => ['nullable', Rule::in(['M', 'F', 'otro'])],
        ];
    }

    /** @return array<string, string|null> */
    private function normalizedInput(): array
    {
        $fields = ['nombre', 'apellido', 'dni', 'email', 'telefono', 'direccion', 'ciudad'];
        $normalized = [];

        foreach ($fields as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $normalized[$field] = $value === '' ? null : $value;
        }

        if (isset($normalized['email'])) {
            $normalized['email'] = mb_strtolower($normalized['email']);
        }

        return $normalized;
    }
}
