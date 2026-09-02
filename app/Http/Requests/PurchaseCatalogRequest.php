<?php

namespace App\Http\Requests;

use App\Models\Compra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Compra::class) ?? false;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->where('tienda_id', $storeId)],
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
