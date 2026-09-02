<?php

namespace App\Services;

use App\Models\Venta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StoreLogoStorage
{
    private const DISK = 'public';

    public function store(UploadedFile $logo, int $storeId): string
    {
        $extension = strtolower($logo->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = Storage::disk(self::DISK)->putFileAs(
            "tiendas/{$storeId}/logos",
            $logo,
            Str::uuid().'.'.$extension
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No fue posible almacenar el logo de la tienda.');
        }

        return $path;
    }

    public function snapshot(?string $path, int $storeId): ?string
    {
        if (! $this->isCurrentLogoPath($path, $storeId) || ! Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $hash = hash('sha256', Storage::disk(self::DISK)->get($path));
        $snapshotPath = "comprobantes/{$storeId}/logos/{$hash}.{$extension}";
        if (! Storage::disk(self::DISK)->exists($snapshotPath)
            && ! Storage::disk(self::DISK)->copy($path, $snapshotPath)) {
            throw new RuntimeException('No fue posible conservar el logo del comprobante.');
        }

        return $snapshotPath;
    }

    public function deleteCurrentIfUnreferenced(?string $path, int $storeId): bool
    {
        if (! $this->isCurrentLogoPath($path, $storeId) || $this->isReferencedByReceipt($path, $storeId)) {
            return false;
        }

        $deleted = Storage::disk(self::DISK)->delete($path);
        if (! $deleted && Storage::disk(self::DISK)->exists($path)) {
            Log::warning('No fue posible eliminar el logo anterior de la tienda.', compact('storeId', 'path'));
        }

        return $deleted;
    }

    public function deleteNew(?string $path, int $storeId): bool
    {
        return $this->isCurrentLogoPath($path, $storeId)
            && Storage::disk(self::DISK)->delete($path);
    }

    public function isCurrentLogoPath(?string $path, int $storeId): bool
    {
        return is_string($path)
            && preg_match('#^tiendas/'.preg_quote((string) $storeId, '#').'/logos/[a-f0-9-]+\.(?:png|jpe?g|webp|svg)$#i', $path) === 1
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    private function isReferencedByReceipt(string $path, int $storeId): bool
    {
        return Venta::query()
            ->where('tienda_id', $storeId)
            ->whereNotNull('commercial_snapshot')
            ->select(['id', 'commercial_snapshot'])
            ->lazyById(100)
            ->contains(fn (Venta $sale): bool => data_get($sale->commercial_snapshot, 'store.logo') === $path);
    }
}
