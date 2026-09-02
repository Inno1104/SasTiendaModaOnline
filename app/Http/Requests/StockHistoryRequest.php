<?php

namespace App\Http\Requests;

use App\Models\ProductoVariante;
use App\Models\StockMovimiento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductoVariante::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['nullable', Rule::in([
                StockMovimiento::TIPO_ENTRADA, StockMovimiento::TIPO_SALIDA,
                StockMovimiento::TIPO_AJUSTE, StockMovimiento::TIPO_VENTA,
                StockMovimiento::TIPO_COMPRA, StockMovimiento::TIPO_ANULACION,
                StockMovimiento::TIPO_DEVOLUCION, StockMovimiento::TIPO_CONCILIACION,
            ])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'movimientos_page' => ['nullable', 'integer', 'min:1'],
            'conteos_page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
