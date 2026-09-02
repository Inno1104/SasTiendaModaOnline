<?php

namespace App\Http\Requests;

use App\Services\PublicCartManager;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePublicCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:'.PublicCartManager::MAX_QUANTITY_PER_LINE],
        ];
    }

    public function messages(): array
    {
        return [
            'cantidad.required' => 'Indica la cantidad.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad mínima es 1.',
            'cantidad.max' => 'La cantidad solicitada excede el límite permitido.',
        ];
    }
}
