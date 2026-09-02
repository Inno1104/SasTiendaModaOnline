<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('venta')) === true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'motivo.min' => 'El motivo debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }
}
