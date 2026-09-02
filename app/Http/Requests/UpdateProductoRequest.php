<?php

namespace App\Http\Requests;

use App\Models\Producto;

class UpdateProductoRequest extends ProductDataRequest
{
    public function authorize(): bool
    {
        $product = $this->route('producto');

        return $product instanceof Producto
            && ($this->user()?->can('update', $product) ?? false);
    }

    public function rules(): array
    {
        return $this->productRules();
    }
}
