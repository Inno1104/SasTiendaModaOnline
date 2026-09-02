<?php

namespace App\Http\Requests;

use App\Support\SaleLimits;
use Illuminate\Foundation\Http\FormRequest;

class CloseCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('close', $this->route('caja')) === true;
    }

    public function rules(): array
    {
        return [
            'efectivo_contado' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.SaleLimits::MAX_MONEY],
            'observaciones_cierre' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'efectivo_contado.required' => 'Debes ingresar el efectivo contado antes de cerrar.',
            'efectivo_contado.numeric' => 'El efectivo contado debe ser un valor numérico.',
            'efectivo_contado.min' => 'El efectivo contado no puede ser negativo.',
            'efectivo_contado.max' => 'El efectivo contado supera el límite permitido.',
            'observaciones_cierre.max' => 'La observación de cierre no puede superar los 500 caracteres.',
        ];
    }
}
