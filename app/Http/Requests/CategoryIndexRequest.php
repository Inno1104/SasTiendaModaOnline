<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Categoria::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['todos', 'activo', 'inactivo'])],
            'genero' => ['nullable', Rule::in(['todos', 'damas', 'caballeros', 'ninos', 'unisex'])],
            'orden' => ['nullable', Rule::in(['nombre_asc', 'nombre_desc', 'recientes', 'antiguas'])],
            'edit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'estado', 'genero', 'orden', 'edit'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
