<?php

namespace App\Http\Requests;

use App\Models\Venta;
use App\Support\PaymentMethods;
use App\Support\SaleLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Venta::class) === true;
    }

    public function rules(): array
    {
        $tiendaId = $this->user()->tienda_id;

        return [
            'operacion_id' => ['nullable', 'uuid'],
            'cliente_id' => [
                'nullable',
                Rule::exists('clientes', 'id')->where(
                    fn ($query) => $query->where('tienda_id', $tiendaId)->where('activo', true)
                ),
            ],
            'tipo_comprobante' => ['required', Rule::in(['boleta', 'factura', 'ticket'])],
            'metodo_pago' => ['required_without:pagos', Rule::in(PaymentMethods::ALL)],
            'monto_pagado' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.SaleLimits::MAX_MONEY],
            'referencia_pago' => ['nullable', 'string', 'max:100'],
            'pagos' => ['nullable', 'array', 'min:1', 'max:5'],
            'pagos.*.metodo' => ['required_with:pagos', Rule::in(PaymentMethods::ALL)],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.SaleLimits::MAX_MONEY],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
            'descuento_global' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.SaleLimits::MAX_MONEY],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.SaleLimits::MAX_ITEMS],
            'items.*.producto_id' => [
                'required',
                'integer',
                Rule::exists('productos', 'id')->where(
                    fn ($query) => $query->where('tienda_id', $tiendaId)->where('activo', true)
                ),
            ],
            'items.*.variante_id' => [
                'required',
                'integer',
                Rule::exists('producto_variantes', 'id')->where(
                    fn ($query) => $query->where('tienda_id', $tiendaId)
                ),
            ],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.SaleLimits::MAX_ITEM_QUANTITY],
            'items.*.descuento' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.SaleLimits::MAX_MONEY],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.*.variante_id.required' => 'Selecciona la talla y el color del producto.',
            'items.*.variante_id.exists' => 'Una de las variantes no está disponible en esta tienda.',
            'items.*.producto_id.exists' => 'Uno de los productos no está disponible en esta tienda.',
            'cliente_id.exists' => 'El cliente seleccionado no está disponible en esta tienda.',
            'metodo_pago.in' => 'El método de pago seleccionado no es válido.',
            'pagos.*.metodo.in' => 'Uno de los métodos de pago no es válido.',
            'tipo_comprobante.in' => 'El tipo de comprobante seleccionado no es válido.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $payments = $this->input('pagos');
            if (is_array($payments)) {
                foreach ($payments as $index => $payment) {
                    $method = (string) ($payment['metodo'] ?? '');
                    $reference = trim((string) ($payment['referencia'] ?? ''));
                    if (PaymentMethods::requiresReference($method) && $reference === '') {
                        $validator->errors()->add(
                            "pagos.$index.referencia",
                            'Ingresa la referencia del pago electrónico.'
                        );
                    }
                }

                return;
            }

            $method = (string) $this->input('metodo_pago', '');
            if (PaymentMethods::requiresReference($method) && trim((string) $this->input('referencia_pago')) === '') {
                $validator->errors()->add('referencia_pago', 'Ingresa la referencia del pago electrónico.');
            }
        }];
    }
}
