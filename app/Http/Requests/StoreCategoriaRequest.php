<?php

namespace App\Http\Requests;

use App\Models\Categoria;

class StoreCategoriaRequest extends CategoryDataRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Categoria::class) ?? false;
    }

    public function rules(): array
    {
        return $this->categoryRules();
    }

    protected function defaultActiveState(): bool
    {
        return true;
    }
}
