<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final class SafeStoreLogo implements ValidationRule
{
    public const MAX_KILOBYTES = 3072;

    /** @var array<string, list<string>> */
    private const RASTER_MIME_TYPES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('No fue posible leer el archivo seleccionado.');

            return;
        }

        if ($value->getSize() > self::MAX_KILOBYTES * 1024) {
            $fail('El logo no puede superar los 3 MB.');

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if ($extension === 'svg') {
            $this->validateSvg($value, $fail);

            return;
        }

        if (! isset(self::RASTER_MIME_TYPES[$extension])) {
            $fail('El logo debe ser PNG, JPG, JPEG, WebP o SVG seguro.');

            return;
        }

        if (! in_array($value->getMimeType(), self::RASTER_MIME_TYPES[$extension], true)) {
            $fail('El contenido del archivo no coincide con un formato de imagen permitido.');

            return;
        }

        $dimensions = @getimagesize($value->getRealPath());
        if ($dimensions === false) {
            $fail('El archivo no contiene una imagen válida.');

            return;
        }

        [$width, $height] = $dimensions;
        if ($width < 64 || $height < 64 || $width > 2500 || $height > 2500) {
            $fail('El logo debe medir entre 64×64 y 2500×2500 píxeles; se recomienda 500×500.');
        }
    }

    private function validateSvg(UploadedFile $file, Closure $fail): void
    {
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents) || preg_match('/<svg\b/i', $contents) !== 1) {
            $fail('El archivo SVG no es válido.');

            return;
        }

        $decodedContents = html_entity_decode($contents, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $unsafePatterns = [
            '/<!DOCTYPE|<!ENTITY/i',
            '/<(?:script|style|foreignObject|iframe|object|embed|audio|video)\b/i',
            '/\son[a-z]+\s*=/i',
            '/(?:javascript|vbscript|data)\s*:/i',
            '/(?:href|xlink:href)\s*=\s*["\']\s*(?:https?:|\/\/)/i',
            '/(?:href|xlink:href)\s*=\s*(?!["\']#)/i',
            '/@import|expression\s*\(|url\s*\(\s*["\']?\s*(?:https?:|\/\/|data:)/i',
        ];

        foreach ($unsafePatterns as $pattern) {
            if (preg_match($pattern, $decodedContents) === 1) {
                $fail('El SVG contiene scripts, enlaces externos o contenido no permitido.');

                return;
            }
        }
    }
}
