<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class SalesReportRequest extends FormRequest
{
    public const MAX_RANGE_DAYS = 366;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'desde' => $this->input('desde', now()->startOfMonth()->toDateString()),
            'hasta' => $this->input('hasta', now()->toDateString()),
        ]);
    }

    public function rules(): array
    {
        return [
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($validator->errors()->hasAny(['desde', 'hasta'])) {
                return;
            }

            $days = CarbonImmutable::parse($this->input('desde'))
                ->diffInDays(CarbonImmutable::parse($this->input('hasta')));

            if ($days > self::MAX_RANGE_DAYS) {
                $validator->errors()->add('hasta', 'El reporte no puede superar 366 días.');
            }
        }];
    }
}
