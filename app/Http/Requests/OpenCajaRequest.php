<?php

namespace App\Http\Requests;

use App\Models\Caja;
use Illuminate\Foundation\Http\FormRequest;

class OpenCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('open', Caja::class) === true;
    }

    public function rules(): array
    {
        return [
            'monto_inicial' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_inicial.required' => 'Debes indicar el efectivo inicial.',
            'monto_inicial.numeric' => 'El efectivo inicial debe ser un valor numérico.',
            'monto_inicial.min' => 'El efectivo inicial no puede ser negativo.',
            'monto_inicial.max' => 'El efectivo inicial supera el límite permitido.',
            'observaciones.max' => 'La observación no puede superar los 500 caracteres.',
        ];
    }
}
