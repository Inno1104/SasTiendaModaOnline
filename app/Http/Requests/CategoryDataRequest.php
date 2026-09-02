<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CategoryDataRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function categoryRules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'genero' => ['required', Rule::in(['damas', 'caballeros', 'ninos', 'unisex'])],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('nombre'));
        $description = trim((string) $this->input('descripcion'));

        $this->merge([
            'nombre' => $name === '' ? null : $name,
            'descripcion' => $description === '' ? null : $description,
            'activo' => $this->exists('activo') ? $this->boolean('activo') : $this->defaultActiveState(),
        ]);
    }

    abstract protected function defaultActiveState(): bool;
}
