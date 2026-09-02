<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductImageStorage
{
    private const DISK = 'public';

    private const DIRECTORY = 'productos';

    public function store(UploadedFile $image, int $storeId): string
    {
        $path = Storage::disk(self::DISK)->putFile(self::DIRECTORY.'/'.$storeId, $image);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No fue posible almacenar la imagen del producto.');
        }

        return $path;
    }

    public function delete(?string $path): bool
    {
        if (! $this->isManagedPath($path)) {
            return false;
        }

        $deleted = Storage::disk(self::DISK)->delete($path);
        if (! $deleted) {
            Log::warning('No fue posible eliminar una imagen anterior del producto.', ['path' => $path]);
        }

        return $deleted;
    }

    private function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::DIRECTORY.'/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }
}
