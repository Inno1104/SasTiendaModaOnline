<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('return', $this->route('venta')) === true;
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:5', 'max:500']];
    }
}
