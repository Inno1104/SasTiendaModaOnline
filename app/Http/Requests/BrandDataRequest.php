<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BrandDataRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function brandRules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'activo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('nombre'));

        $this->merge([
            'nombre' => $name === '' ? null : $name,
            'activo' => $this->exists('activo') ? $this->boolean('activo') : $this->defaultActiveState(),
        ]);
    }

    abstract protected function defaultActiveState(): bool;
}
