<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('venta')) === true;
    }

    public function rules(): array
    {
        return [
            // Unknown formats deliberately fall back to A4 for backwards compatibility.
            'format' => ['nullable', 'string', 'max:20'],
            'download' => ['nullable', 'boolean'],
        ];
    }
}
