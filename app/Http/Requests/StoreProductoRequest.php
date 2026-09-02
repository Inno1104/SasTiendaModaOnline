<?php

namespace App\Http\Requests;

use App\Models\Producto;

class StoreProductoRequest extends ProductDataRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Producto::class) ?? false;
    }

    public function rules(): array
    {
        return $this->productRules();
    }

    protected function defaultActiveState(): bool
    {
        return true;
    }
}
