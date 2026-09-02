<?php

namespace App\Http\Requests;

use App\Models\Tienda;
use App\Rules\SafeStoreLogo;
use App\Services\StoreIdentityNormalizer;
use App\Support\StoreLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->user()?->tienda;

        return $store instanceof Tienda
            && ($this->user()?->can('update', $store) ?? false);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'ruc' => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Z0-9-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:254'],
            'telefono' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'pais_codigo' => ['nullable', 'string', 'size:2', Rule::in(array_keys(StoreLocation::COUNTRIES))],
            'provincia' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'sitio_web' => ['nullable', 'string', 'max:2048', 'url:https'],
            'redes_sociales' => ['nullable', 'array:instagram,facebook,tiktok'],
            'redes_sociales.instagram' => ['nullable', 'string', 'max:2048', 'url:https'],
            'redes_sociales.facebook' => ['nullable', 'string', 'max:2048', 'url:https'],
            'redes_sociales.tiktok' => ['nullable', 'string', 'max:2048', 'url:https'],
            'logo' => ['nullable', 'file', new SafeStoreLogo],
            'eliminar_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre comercial es obligatorio.',
            'nombre.min' => 'El nombre comercial debe tener al menos 2 caracteres.',
            'ruc.regex' => 'La identificación fiscal solo puede contener letras, números y guiones.',
            'email.email' => 'Ingresa un correo de contacto válido.',
            'telefono.regex' => 'Ingresa un teléfono válido de 7 a 15 dígitos.',
            'whatsapp.regex' => 'Ingresa un WhatsApp válido de 7 a 15 dígitos.',
            'pais_codigo.size' => 'Selecciona un código de país válido.',
            'pais_codigo.in' => 'Selecciona un país admitido.',
            'sitio_web.url' => 'El sitio web debe ser una URL HTTPS válida.',
            'redes_sociales.*.url' => 'Cada red social debe utilizar una URL HTTPS válida.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $country = $this->input('pais_codigo');
            $province = $this->input('provincia');
            $city = $this->input('ciudad');

            if (($province !== null || $city !== null) && $country === null) {
                $validator->errors()->add('pais_codigo', 'Selecciona el país antes de indicar provincia o ciudad.');
            }
            if ($city !== null && $province === null) {
                $validator->errors()->add('provincia', 'Indica la provincia o estado antes de la ciudad.');
            }
            if ($this->hasFile('logo') && $this->boolean('eliminar_logo')) {
                $validator->errors()->add('logo', 'No puedes reemplazar y eliminar el logo en la misma operación.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'nombre', 'ruc', 'email', 'telefono', 'whatsapp', 'direccion',
            'pais_codigo', 'provincia', 'ciudad', 'sitio_web', 'redes_sociales',
        ];
        $data = $this->only($fields);
        if ($this->hasAny(['pais_codigo', 'provincia', 'ciudad'])) {
            $data += ['pais_codigo' => null, 'provincia' => null, 'ciudad' => null];
        }
        if ($this->has('redes_sociales')) {
            $data['redes_sociales'] = $this->input('redes_sociales');
        }
        $this->merge(app(StoreIdentityNormalizer::class)->normalize($data));
        $this->merge(['eliminar_logo' => $this->boolean('eliminar_logo')]);
    }
}
