<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['todos', 'activo', 'inactivo'])],
            'rol_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->whereIn('nombre', Rol::ASSIGNABLE_NAMES)),
            ],
            'orden' => ['nullable', Rule::in(['nombre_asc', 'nombre_desc', 'recientes', 'antiguos'])],
            'por_pagina' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
            'edit' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('edit') && ! is_scalar($this->input('edit'))) {
            abort(404);
        }

        foreach (['buscar', 'estado', 'rol_id', 'orden', 'por_pagina', 'edit'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
