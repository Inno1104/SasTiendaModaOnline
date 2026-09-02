<?php

namespace App\Http\Requests;

use App\Models\Tienda;
use App\Support\StorefrontConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateStorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        $store = $this->user()?->tienda;

        return $store instanceof Tienda && ($this->user()?->can('update', $store) ?? false);
    }

    public function rules(): array
    {
        return [
            'publicada' => ['required', 'boolean'],
            'descripcion_publica' => ['nullable', 'string', 'max:500'],
            'color_primario' => ['required', 'string', 'regex:/^#[0-9A-F]{6}$/'],
            'horarios' => ['required', 'array:'.implode(',', array_keys(StorefrontConfiguration::DAYS))],
            'horarios.*' => ['required', 'array:activo,apertura,cierre'],
            'horarios.*.activo' => ['required', 'boolean'],
            'horarios.*.apertura' => ['nullable', 'date_format:H:i'],
            'horarios.*.cierre' => ['nullable', 'date_format:H:i'],
            'condiciones_venta' => ['nullable', 'array:'.implode(',', array_keys(StorefrontConfiguration::CONDITION_FIELDS))],
            'condiciones_venta.*' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'color_primario.regex' => 'Selecciona un color hexadecimal válido.',
            'horarios.required' => 'Configura los horarios de atención.',
            'horarios.*.apertura.date_format' => 'La hora de apertura debe utilizar el formato HH:mm.',
            'horarios.*.cierre.date_format' => 'La hora de cierre debe utilizar el formato HH:mm.',
            'condiciones_venta.*.max' => 'Cada condición admite hasta 3000 caracteres.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (StorefrontConfiguration::DAYS as $day => $label) {
                $schedule = $this->input("horarios.$day", []);
                if (! filter_var($schedule['activo'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $opening = $schedule['apertura'] ?? null;
                $closing = $schedule['cierre'] ?? null;
                if (! $opening) {
                    $validator->errors()->add("horarios.$day.apertura", "Indica la apertura del $label.");
                }
                if (! $closing) {
                    $validator->errors()->add("horarios.$day.cierre", "Indica el cierre del $label.");
                }
                if ($opening && $closing && $closing <= $opening) {
                    $validator->errors()->add("horarios.$day.cierre", "El cierre del $label debe ser posterior a la apertura.");
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $schedules = [];
        foreach (StorefrontConfiguration::DAYS as $day => $label) {
            $active = $this->boolean("horarios.$day.activo");
            $schedules[$day] = [
                'activo' => $active,
                'apertura' => $active ? $this->nullableText($this->input("horarios.$day.apertura")) : null,
                'cierre' => $active ? $this->nullableText($this->input("horarios.$day.cierre")) : null,
            ];
        }

        $conditions = is_array($this->input('condiciones_venta'))
            ? $this->input('condiciones_venta')
            : [];
        foreach (StorefrontConfiguration::CONDITION_FIELDS as $field => $label) {
            $value = $this->nullableText($this->input("condiciones_venta.$field"));
            if ($value !== null) {
                $conditions[$field] = $value;
            } else {
                unset($conditions[$field]);
            }
        }

        $this->merge([
            'publicada' => $this->boolean('publicada'),
            'descripcion_publica' => $this->nullableText($this->input('descripcion_publica')),
            'color_primario' => mb_strtoupper(trim((string) $this->input('color_primario', '#E8398C'))),
            'horarios' => $schedules,
            'condiciones_venta' => $conditions === [] ? null : $conditions,
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }
}
