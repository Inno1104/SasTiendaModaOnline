<?php

namespace App\Http\Requests;

use App\Models\StockConteo;
use Illuminate\Foundation\Http\FormRequest;

class ReconcileStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $count = $this->route('conteo');

        return $count instanceof StockConteo
            && ($this->user()?->can('reconcileStock', $count->variante) ?? false);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
