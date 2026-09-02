<?php

namespace App\Http\Requests;

use App\Models\Marca;

class UpdateMarcaRequest extends BrandDataRequest
{
    public function authorize(): bool
    {
        $brand = $this->route('marca');

        return $brand instanceof Marca && ($this->user()?->can('update', $brand) ?? false);
    }

    public function rules(): array
    {
        return $this->brandRules();
    }

    protected function defaultActiveState(): bool
    {
        return false;
    }
}
