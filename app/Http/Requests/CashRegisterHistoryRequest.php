<?php

namespace App\Http\Requests;

use App\Models\Caja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashRegisterHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Caja::class) === true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'estado' => ['nullable', Rule::in(['abierta', 'cerrada'])],
            'conciliacion' => ['nullable', Rule::in(['exacto', 'sobrante', 'faltante'])],
            'responsable_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tienda_id', $this->user()->tienda_id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'q.max' => 'La búsqueda no puede superar los 100 caracteres.',
            'desde.date_format' => 'La fecha inicial no es válida.',
            'hasta.date_format' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'estado.in' => 'El estado de caja seleccionado no es válido.',
            'conciliacion.in' => 'El resultado de conciliación seleccionado no es válido.',
            'responsable_id.exists' => 'El responsable seleccionado no pertenece a esta tienda.',
        ];
    }
}
