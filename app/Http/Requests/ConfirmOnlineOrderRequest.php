<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmOnlineOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operacion_id' => ['required', 'uuid'],
            'confirma_pedido' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'operacion_id.required' => 'La operación del pedido no es válida. Actualiza la página.',
            'operacion_id.uuid' => 'La operación del pedido no es válida. Actualiza la página.',
            'confirma_pedido.accepted' => 'Confirma que revisaste el pedido antes de continuar.',
        ];
    }
}
