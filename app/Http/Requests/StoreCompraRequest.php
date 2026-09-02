<?php

namespace App\Http\Requests;

use App\Models\Compra;
use App\Services\PurchaseCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Compra::class) ?? false;
    }

    public function rules(): array
    {
        $storeId = (int) $this->user()->tienda_id;
        $operationId = $this->input('operacion_id');

        return [
            'operacion_id' => ['nullable', 'uuid'],
            'proveedor_id' => [
                'required',
                'integer',
                Rule::exists('proveedores', 'id')->where(function ($query) use ($storeId, $operationId) {
                    $query->where('tienda_id', $storeId)
                        ->where(function ($status) use ($storeId, $operationId) {
                            $status->where('activo', true);
                            if ($operationId) {
                                $status->orWhereExists(function ($purchases) use ($storeId, $operationId) {
                                    $purchases->selectRaw('1')
                                        ->from('compras')
                                        ->whereColumn('compras.proveedor_id', 'proveedores.id')
                                        ->where('compras.tienda_id', $storeId)
                                        ->where('compras.operacion_id', $operationId);
                                });
                            }
                        });
                }),
            ],
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variante_id' => [
                'required',
                'integer',
                Rule::exists('producto_variantes', 'id')->where(function ($query) use ($storeId, $operationId) {
                    $query->where('tienda_id', $storeId)
                        ->where(function ($availability) use ($storeId, $operationId) {
                            $availability->whereExists(function ($products) use ($storeId) {
                                $products->selectRaw('1')
                                    ->from('productos')
                                    ->whereColumn('productos.id', 'producto_variantes.producto_id')
                                    ->where('productos.tienda_id', $storeId)
                                    ->where('productos.activo', true);
                            });
                            if ($operationId) {
                                $availability->orWhereExists(function ($details) use ($storeId, $operationId) {
                                    $details->selectRaw('1')
                                        ->from('detalle_compras')
                                        ->join('compras', 'compras.id', '=', 'detalle_compras.compra_id')
                                        ->whereColumn('detalle_compras.variante_id', 'producto_variantes.id')
                                        ->where('compras.tienda_id', $storeId)
                                        ->where('compras.operacion_id', $operationId);
                                });
                            }
                        });
                }),
            ],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.PurchaseCalculator::MAX_QUANTITY],
            'items.*.precio' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.exists' => 'Selecciona un proveedor activo de esta tienda.',
            'fecha.before_or_equal' => 'La fecha de la compra no puede estar en el futuro.',
            'items.required' => 'Agrega al menos un producto a la compra.',
            'items.max' => 'Una compra no puede contener más de 100 líneas.',
            'items.*.variante_id.required' => 'Selecciona una variante en cada línea.',
            'items.*.variante_id.exists' => 'Una variante no está activa o no pertenece a esta tienda.',
            'items.*.cantidad.required' => 'Ingresa la cantidad de cada variante.',
            'items.*.precio.required' => 'Ingresa el precio de compra de cada variante.',
            'items.*.precio.decimal' => 'El precio debe tener como máximo dos decimales.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('observaciones')) {
            $notes = trim((string) $this->input('observaciones'));
            $this->merge(['observaciones' => $notes === '' ? null : $notes]);
        }
    }
}
