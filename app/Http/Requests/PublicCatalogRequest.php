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
            'tallas' => ['nullable', 'array', 'max:20'],
            'tallas.*' => ['string', 'max:30'],
            'colores' => ['nullable', 'array', 'max:30'],
            'colores.*' => ['string', 'max:60'],
            'precio_min' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'precio_max' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'gte:precio_min'],
            'orden' => ['nullable', 'in:destacados,recientes,nombre,precio_asc,precio_desc'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = trim(preg_replace('/\s+/u', ' ', (string) $this->input('q')));
        $category = mb_strtolower(trim((string) $this->input('categoria')));
        $normalizeList = static fn ($values): array => array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            is_array($values) ? $values : []
        ))));

        $this->merge([
            'q' => $search === '' ? null : $search,
            'categoria' => $category === '' ? null : $category,
            'tallas' => $normalizeList($this->input('tallas')),
            'colores' => $normalizeList($this->input('colores')),
            'orden' => $this->input('orden') ?: 'destacados',
        ]);
    }
}
