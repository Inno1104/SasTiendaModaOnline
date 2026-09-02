<?php

namespace App\Http\Requests;

use App\Models\Venta;
use App\Support\SaleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Venta::class) === true;
    }

    public function rules(): array
    {
        $storeId = $this->user()->tienda_id;

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'periodo' => ['nullable', Rule::in(['hoy', '7_dias', '30_dias', 'mes_actual'])],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(SaleStatus::ALL)],
            'metodo' => ['nullable', Rule::in(['efectivo', 'tarjeta', 'transferencia', 'yape', 'plin'])],
            'vendedor_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tienda_id', $storeId)],
            'caja_id' => ['nullable', 'integer', Rule::exists('caja', 'id')->where('tienda_id', $storeId)],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 100 caracteres.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'periodo.in' => 'El periodo seleccionado no es válido.',
            'metodo.in' => 'El método de pago seleccionado no es válido.',
            'vendedor_id.exists' => 'El vendedor seleccionado no pertenece a esta tienda.',
            'caja_id.exists' => 'La caja seleccionada no pertenece a esta tienda.',
        ];
    }
}
