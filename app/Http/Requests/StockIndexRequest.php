<?php

namespace App\Http\Requests;

use App\Models\ProductoVariante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductoVariante::class) ?? false;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria_id' => [
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where('tienda_id', $storeId),
            ],
            'filtro' => ['nullable', Rule::in(['todos', 'bajo', 'agotado', 'disponible'])],
            'producto_estado' => ['nullable', Rule::in(['activo', 'inactivo', 'todos'])],
            'orden' => ['nullable', Rule::in(['stock_asc', 'stock_desc', 'producto_asc'])],
            'por_pagina' => ['nullable', 'integer', Rule::in([20, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'categoria_id', 'filtro', 'producto_estado', 'orden', 'por_pagina'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
