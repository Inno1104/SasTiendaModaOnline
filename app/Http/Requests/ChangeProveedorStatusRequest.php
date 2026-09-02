<?php

namespace App\Http\Requests;

use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;

class ChangeProveedorStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('proveedor');

        return $supplier instanceof Proveedor
            && ($this->user()?->can('update', $supplier) ?? false);
    }

    public function rules(): array
    {
        return ['activo' => ['required', 'boolean']];
    }
}
