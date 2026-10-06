<?php

namespace App\Http\Requests;

use App\Models\EcuadorUbicacion;
use App\Support\StoreLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreGuestCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente' => ['required', 'array:nombre,apellido,dni,email,telefono'],
            'cliente.nombre' => ['required', 'string', 'max:100', 'regex:/^[\pL\pM .\'-]+$/u'],
            'cliente.apellido' => ['required', 'string', 'max:100', 'regex:/^[\pL\pM .\'-]+$/u'],
            'cliente.dni' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[A-Z0-9.-]+$/i'],
            'cliente.email' => ['required', 'email:rfc', 'max:150'],
            'cliente.telefono' => ['required', 'string', 'regex:/^\+?[0-9]{7,20}$/'],

            'entrega' => ['required', 'array:pais_codigo,provincia,ciudad,codigo_postal,direccion,referencia'],
            'entrega.pais_codigo' => ['required', 'string', 'size:2', Rule::in(array_keys(StoreLocation::COUNTRIES))],
            'entrega.provincia' => ['required', 'string', 'max:100'],
            'entrega.ciudad' => ['required', 'string', 'max:100'],
            'entrega.codigo_postal' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9 -]+$/i'],
            'entrega.direccion' => ['required', 'string', 'max:255'],
            'entrega.referencia' => ['nullable', 'string', 'max:255'],

            'fiscal' => ['required', 'array:requiere_factura,tipo_comprobante,usar_datos_contacto,tipo_identificacion,identificacion,razon_social,direccion_fiscal'],
            'fiscal.requiere_factura' => ['required', 'boolean'],
            'fiscal.tipo_comprobante' => ['required', Rule::in(['ticket', 'boleta', 'factura'])],
            'fiscal.usar_datos_contacto' => ['required', 'boolean'],
            'fiscal.tipo_identificacion' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->boolean('fiscal.usar_datos_contacto')),
                Rule::in(['cedula_dni', 'ruc_nit', 'pasaporte', 'otro']),
            ],
            'fiscal.identificacion' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->boolean('fiscal.usar_datos_contacto')),
                'string',
                'min:5',
                'max:30',
                'regex:/^[A-Z0-9.-]+$/i',
            ],
            'fiscal.razon_social' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->boolean('fiscal.usar_datos_contacto')),
                'string',
                'max:180',
            ],
            'fiscal.direccion_fiscal' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->boolean('fiscal.usar_datos_contacto')),
                'string',
                'max:255',
            ],
            'acepta_privacidad' => ['accepted'],
            'confirmar_pedido' => ['sometimes', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente.nombre.required' => 'Ingresa tus nombres.',
            'cliente.nombre.regex' => 'Los nombres contienen caracteres no permitidos.',
            'cliente.apellido.required' => 'Ingresa tus apellidos.',
            'cliente.apellido.regex' => 'Los apellidos contienen caracteres no permitidos.',
            'cliente.dni.required' => 'Ingresa tu DNI o documento de identidad.',
            'cliente.dni.regex' => 'El DNI contiene caracteres no permitidos.',
            'cliente.email.required' => 'Ingresa tu correo electrónico.',
            'cliente.email.email' => 'Ingresa un correo electrónico válido.',
            'cliente.telefono.required' => 'Ingresa un teléfono de contacto.',
            'cliente.telefono.regex' => 'Ingresa el teléfono con código de país y solo números.',
            'entrega.pais_codigo.required' => 'Selecciona el país de entrega.',
            'entrega.pais_codigo.in' => 'El país seleccionado no está permitido.',
            'entrega.provincia.required' => 'Ingresa la provincia o estado.',
            'entrega.ciudad.required' => 'Ingresa la ciudad o cantón.',
            'entrega.direccion.required' => 'Ingresa la dirección de entrega.',
            'fiscal.tipo_identificacion.required' => 'Selecciona el tipo de identificación fiscal.',
            'fiscal.identificacion.required' => 'Ingresa la identificación fiscal.',
            'fiscal.identificacion.regex' => 'La identificación fiscal tiene un formato inválido.',
            'fiscal.razon_social.required' => 'Ingresa el nombre o razón social para facturación.',
            'acepta_privacidad.accepted' => 'Debes aceptar el tratamiento de datos para continuar.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['entrega.pais_codigo', 'entrega.provincia', 'entrega.ciudad'])) {
                return;
            }
            if ($this->input('entrega.pais_codigo') !== 'EC') {
                return;
            }

            $exists = EcuadorUbicacion::query()
                ->where('provincia', $this->input('entrega.provincia'))
                ->where('canton', $this->input('entrega.ciudad'))
                ->exists();

            if (! $exists) {
                $validator->errors()->add('entrega.ciudad', 'Selecciona un cantón válido para la provincia indicada.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $customer = (array) $this->input('cliente', []);
        $delivery = (array) $this->input('entrega', []);
        $fiscal = (array) $this->input('fiscal', []);

        $legacyInvoice = filter_var($fiscal['requiere_factura'] ?? false, FILTER_VALIDATE_BOOL);
        $fiscal['tipo_comprobante'] = $fiscal['tipo_comprobante'] ?? ($legacyInvoice ? 'factura' : 'boleta');
        $fiscal['usar_datos_contacto'] = array_key_exists('usar_datos_contacto', $fiscal)
            ? filter_var($fiscal['usar_datos_contacto'], FILTER_VALIDATE_BOOL)
            : ! $legacyInvoice;

        foreach (['nombre', 'apellido'] as $field) {
            $customer[$field] = $this->normalizeText($customer[$field] ?? null);
        }
        $customer['email'] = mb_strtolower((string) $this->normalizeText($customer['email'] ?? null));
        $customer['dni'] = mb_strtoupper(preg_replace('/\s+/u', '', trim((string) ($customer['dni'] ?? ''))));
        $customer['telefono'] = $this->normalizePhone($customer['telefono'] ?? null);

        foreach (['provincia', 'ciudad', 'direccion', 'referencia'] as $field) {
            $delivery[$field] = $this->normalizeText($delivery[$field] ?? null);
        }
        $delivery['pais_codigo'] = mb_strtoupper(trim((string) ($delivery['pais_codigo'] ?? '')));
        $delivery['codigo_postal'] = mb_strtoupper((string) $this->normalizeText($delivery['codigo_postal'] ?? null));

        $fiscal['requiere_factura'] = $fiscal['tipo_comprobante'] === 'factura';
        $fiscal['tipo_identificacion'] = $this->normalizeText($fiscal['tipo_identificacion'] ?? null);
        $fiscal['identificacion'] = mb_strtoupper(preg_replace('/\s+/u', '', trim((string) ($fiscal['identificacion'] ?? ''))));
        foreach (['razon_social', 'direccion_fiscal'] as $field) {
            $fiscal[$field] = $this->normalizeText($fiscal[$field] ?? null);
        }

        if ($fiscal['usar_datos_contacto']) {
            $fiscal['tipo_identificacion'] = 'cedula_dni';
            $fiscal['identificacion'] = $customer['dni'];
            $fiscal['razon_social'] = trim(($customer['nombre'] ?? '').' '.($customer['apellido'] ?? ''));
            $fiscal['direccion_fiscal'] = $delivery['direccion'] ?? null;
        }

        $this->merge([
            'cliente' => $customer,
            'entrega' => $delivery,
            'fiscal' => $fiscal,
        ]);
    }

    private function normalizeText(mixed $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePhone(mixed $value): string
    {
        $phone = trim((string) $value);
        $prefix = str_starts_with($phone, '+') ? '+' : '';

        return $prefix.preg_replace('/\D+/', '', $phone);
    }
}
