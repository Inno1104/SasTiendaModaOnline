<?php

namespace App\Http\Requests;

use App\Models\ProductoVariante;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variant = $this->route('variante');

        return $variant instanceof ProductoVariante && ($this->user()?->can('update', $variant) ?? false);
    }

    public function rules(): array
    {
        return [
            'stock_fisico' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'expected_version' => ['required', 'integer', 'min:0'],
            'operacion_id' => ['required', 'uuid'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
