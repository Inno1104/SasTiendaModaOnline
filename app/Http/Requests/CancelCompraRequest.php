<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', $this->route('compra')) === true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:10', 'max:500']];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'motivo.min' => 'El motivo debe contener al menos 10 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('motivo')) {
            $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
        }
    }
}
