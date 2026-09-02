<?php

namespace App\Http\Requests;

use App\Models\Cliente;

class UpdateClienteRequest extends StoreClienteRequest
{
    public function authorize(): bool
    {
        $cliente = $this->route('cliente');

        return $cliente instanceof Cliente
            && ($this->user()?->can('update', $cliente) ?? false);
    }

    public function rules(): array
    {
        $cliente = $this->route('cliente');

        return $this->customerRules($cliente instanceof Cliente ? $cliente : null);
    }
}
