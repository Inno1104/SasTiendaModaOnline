<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ChangeUsuarioStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->can('viewAny', User::class)) {
            return false;
        }

        $managedUser = User::query()
            ->where('tienda_id', $actor->tienda_id)
            ->where('es_super_admin', false)
            ->findOrFail((int) $this->route('usuarioId'));

        return $actor->can('changeStatus', $managedUser);
    }

    public function rules(): array
    {
        return [
            'activo' => ['required', 'boolean'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'activo.required' => 'Debes indicar el estado que tendrá el usuario.',
            'motivo.required' => 'Debes indicar el motivo del cambio de estado.',
            'motivo.min' => 'El motivo debe contener al menos 5 caracteres.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('motivo')) {
            $this->merge(['motivo' => trim((string) $this->input('motivo'))]);
        }
    }
}
