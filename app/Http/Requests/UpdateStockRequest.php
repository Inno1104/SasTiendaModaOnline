<?php

namespace App\Http\Requests;

use App\Models\ProductoVariante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $variant = $this->route('variante');

        return $variant instanceof ProductoVariante
            && ($this->user()?->can('update', $variant) ?? false);
    }

    public function rules(): array
    {
        return [
            'stock' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'stock_minimo' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'expected_version' => ['required', 'integer', 'min:0'],
            'motivo' => [
                Rule::requiredIf(function (): bool {
                    $variant = $this->route('variante');

                    return $variant instanceof ProductoVariante
                        && ((int) $this->input('stock') !== (int) $variant->stock
                            || (int) $this->input('stock_minimo') !== (int) $variant->stock_minimo);
                }),
                'nullable', 'string', 'min:5', 'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debes indicar el motivo del ajuste de inventario.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ];
    }
}
