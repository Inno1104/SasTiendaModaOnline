<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

abstract class UserDataRequest extends FormRequest
{
    protected function commonRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', $this->emailUniqueRule()],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')
                ->where(fn ($query) => $query->whereIn('nombre', Rol::ASSIGNABLE_NAMES))],
            'telefono' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'password' => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre completo es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre no puede superar los 120 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'rol_id.required' => 'Selecciona un rol.',
            'rol_id.exists' => 'El rol seleccionado no está permitido.',
            'telefono.regex' => 'El teléfono debe contener entre 7 y 15 dígitos y puede comenzar con +.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('telefono', ''));
        $international = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $phoneIsValidInput = $phone === '' || preg_match('/^\+?[0-9\s().-]+$/', $phone) === 1;

        $this->merge([
            'name' => preg_replace('/\s+/u', ' ', trim((string) $this->input('name'))) ?? '',
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'telefono' => $phone === '' ? null : ($phoneIsValidInput ? ($international ? '+' : '').$digits : $phone),
        ]);
    }

    abstract protected function emailUniqueRule(): mixed;

    abstract protected function passwordRules(): array;

    protected function securePasswordRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'string', 'max:72', Password::min(8)->letters()->numbers(), 'confirmed'];
    }
}
