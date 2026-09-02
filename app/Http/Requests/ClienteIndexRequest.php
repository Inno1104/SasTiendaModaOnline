<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Cliente::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['todos', 'activo', 'inactivo'])],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'actividad' => ['nullable', Rule::in(['todos', 'con_compras', 'sin_compras'])],
            'registrado_desde' => ['nullable', 'date'],
            'registrado_hasta' => ['nullable', 'date', 'after_or_equal:registrado_desde'],
            'ultima_compra_desde' => ['nullable', 'date'],
            'ultima_compra_hasta' => ['nullable', 'date', 'after_or_equal:ultima_compra_desde'],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguos', 'nombre_asc', 'nombre_desc', 'compras_desc', 'gasto_desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'ciudad'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = trim((string) $this->input($field));
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
