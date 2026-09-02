<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

class ChangeClienteStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');

        return $cliente instanceof Cliente
            && ($this->user()?->can('changeStatus', $cliente) ?? false);
    }

    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('motivo')) {
            $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
        }
    }
}
