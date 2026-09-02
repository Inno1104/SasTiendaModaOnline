<?php

namespace App\Http\Requests;

use App\Models\Marca;

class StoreMarcaRequest extends BrandDataRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Marca::class) ?? false;
    }

    public function rules(): array
    {
        return $this->brandRules();
    }

    protected function defaultActiveState(): bool
    {
        return true;
    }
}
