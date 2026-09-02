<?php

namespace App\Http\Requests;

use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
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
            'categoria_id' => [
                'nullable',
                'integer',
                Rule::exists('categorias', 'id')->where('tienda_id', $storeId),
            ],
            'genero' => ['nullable', Rule::in(['damas', 'caballeros', 'ninos', 'unisex'])],
            'tipo' => ['nullable', Rule::in(['casual', 'deportivo', 'formal', 'otro'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['buscar', 'categoria_id', 'genero', 'tipo'] as $field) {
            if (! $this->exists($field)) {
                continue;
            }

            $value = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }
}
