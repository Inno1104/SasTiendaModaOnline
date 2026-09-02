<?php

namespace App\Http\Requests;

use App\Models\Categoria;

class UpdateCategoriaRequest extends CategoryDataRequest
{
    public function authorize(): bool
    {
        $category = $this->route('categoria');

        return $category instanceof Categoria && ($this->user()?->can('update', $category) ?? false);
    }

    public function rules(): array
    {
        return $this->categoryRules();
    }

    protected function defaultActiveState(): bool
    {
        return false;
    }
}
