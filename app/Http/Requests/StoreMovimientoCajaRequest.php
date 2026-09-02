<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMovimientoCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageMovements', $this->route('caja')) === true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['ingreso', 'egreso'])],
            'concepto' => ['required', 'string', 'min:3', 'max:150'],
            'monto' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.in' => 'El tipo de movimiento no es válido.',
            'concepto.required' => 'Debes indicar el concepto del movimiento.',
            'concepto.min' => 'El concepto debe contener al menos 3 caracteres.',
            'concepto.max' => 'El concepto no puede superar los 150 caracteres.',
            'monto.required' => 'Debes indicar el monto del movimiento.',
            'monto.numeric' => 'El monto debe ser un valor numérico.',
            'monto.gt' => 'El monto debe ser mayor que cero.',
            'monto.max' => 'El monto supera el límite permitido.',
            'observaciones.max' => 'La observación no puede superar los 500 caracteres.',
        ];
    }
}
