<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ! $user->es_super_admin && $user->activo && $user->tienda_id !== null;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')
                ->where('tienda_id', $storeId)
                ->where('activo', true)],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')
                ->where('tienda_id', $storeId)
                ->where('activo', true)],
            'disponibles' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('q')) {
            $search = trim((string) $this->input('q'));
            $this->merge(['q' => $search === '' ? null : $search]);
        }
    }
}
