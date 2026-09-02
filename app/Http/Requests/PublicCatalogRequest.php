<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]+$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = trim(preg_replace('/\s+/u', ' ', (string) $this->input('q')));
        $category = mb_strtolower(trim((string) $this->input('categoria')));

        $this->merge([
            'q' => $search === '' ? null : $search,
            'categoria' => $category === '' ? null : $category,
        ]);
    }
}
