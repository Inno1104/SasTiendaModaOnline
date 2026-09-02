<?php

namespace App\Http\Requests;

use App\Models\Proveedor;

class StoreProveedorRequest extends ProveedorDataRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Proveedor::class) ?? false;
    }

    public function rules(): array
    {
        return $this->supplierRules();
    }
}
