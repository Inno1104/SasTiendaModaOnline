<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Producto::class) ?? false;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('tienda_id', $storeId)],
            'estado' => ['nullable', Rule::in(['todos', 'atencion', 'bajo', 'agotado', 'disponible'])],
            'orden' => ['nullable', Rule::in(['producto_asc', 'stock_asc', 'stock_desc', 'valor_desc'])],
            'por_pagina' => ['nullable', 'integer', Rule::in([25, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'categoria_id', 'estado', 'orden', 'por_pagina'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
