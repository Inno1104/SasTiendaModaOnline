<?php

namespace App\Http\Requests;

use App\Models\Compra;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Compra::class) ?? false;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'buscar' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', Rule::in(['pendiente', 'recibido', 'anulado'])],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->where('tienda_id', $storeId)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'orden' => ['nullable', Rule::in(['recientes', 'antiguas', 'mayor_total', 'menor_total'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('buscar')) {
            $search = trim((string) $this->input('buscar'));
            $this->merge(['buscar' => $search === '' ? null : $search]);
        }

        $this->merge(['orden' => $this->input('orden', 'recientes')]);
    }
}
