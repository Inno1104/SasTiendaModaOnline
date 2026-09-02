<?php

namespace App\Http\Requests;

use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Marca::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['todos', 'activo', 'inactivo'])],
            'orden' => ['nullable', Rule::in(['nombre_asc', 'nombre_desc', 'recientes', 'antiguas'])],
            'edit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'estado', 'orden', 'edit'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
