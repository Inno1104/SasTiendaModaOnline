<?php

namespace App\Http\Requests;

use App\Models\Proveedor;

class UpdateProveedorRequest extends ProveedorDataRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('proveedor');

        return $supplier instanceof Proveedor && ($this->user()?->can('update', $supplier) ?? false);
    }

    public function rules(): array
    {
        $supplier = $this->route('proveedor');

        return $this->supplierRules($supplier instanceof Proveedor ? $supplier : null);
    }
}
