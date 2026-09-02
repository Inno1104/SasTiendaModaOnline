<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $suppliers = DB::table('proveedores')
            ->select('id', 'tienda_id', 'nombre', 'ruc', 'email', 'telefono', 'direccion', 'ciudad', 'pais', 'contacto')
            ->orderBy('id')
            ->get();

        if ($suppliers->contains(fn ($supplier) => $supplier->tienda_id === null)) {
            throw new RuntimeException(
                'Existen proveedores sin tienda. Asígnelos antes de ejecutar esta migración.'
            );
        }

        $normalizedTaxIds = $suppliers->mapWithKeys(fn ($supplier) => [
            $supplier->id => $this->normalizeTaxId($supplier->ruc),
        ]);
        $duplicates = $suppliers
            ->filter(fn ($supplier) => $normalizedTaxIds[$supplier->id] !== null)
            ->groupBy(fn ($supplier) => $supplier->tienda_id.'|'.$normalizedTaxIds[$supplier->id])
            ->first(fn ($group) => $group->count() > 1);

        if ($duplicates) {
            throw new RuntimeException(
                'Existen proveedores con identificaciones fiscales equivalentes dentro de una tienda. Corríjalos antes de ejecutar esta migración.'
            );
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropUnique('proveedores_ruc_unique');
        });

        foreach ($suppliers as $supplier) {
            DB::table('proveedores')->where('id', $supplier->id)->update([
                'nombre' => Str::squish($supplier->nombre),
                'ruc' => $normalizedTaxIds[$supplier->id],
                'email' => $this->normalizeEmail($supplier->email),
                'telefono' => $this->normalizePhone($supplier->telefono),
                'direccion' => $this->normalizeText($supplier->direccion),
                'ciudad' => $this->normalizeText($supplier->ciudad),
                'pais' => $this->normalizeText($supplier->pais),
                'contacto' => $this->normalizeText($supplier->contacto),
            ]);
        }

        Schema::table('proveedores', function (Blueprint $table) {
            $table->unique(['tienda_id', 'ruc'], 'proveedores_tienda_ruc_unique');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE proveedores MODIFY tienda_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE proveedores MODIFY nombre VARCHAR(200) NOT NULL');
            DB::statement('ALTER TABLE proveedores MODIFY ruc VARCHAR(30) NULL');
            DB::statement('ALTER TABLE proveedores MODIFY telefono VARCHAR(21) NULL');
            DB::statement('ALTER TABLE proveedores MODIFY ciudad VARCHAR(100) NULL');
            DB::statement('ALTER TABLE proveedores MODIFY pais VARCHAR(100) NULL');
            DB::statement('ALTER TABLE proveedores MODIFY contacto VARCHAR(150) NULL');
        }
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropUnique('proveedores_tienda_ruc_unique');
            $table->unique('ruc', 'proveedores_ruc_unique');
        });
    }

    private function normalizeTaxId(?string $value): ?string
    {
        $normalized = mb_strtoupper((string) preg_replace('/[^\pL\pN]/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeEmail(?string $value): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $normalized === '' ? null : $normalized;
    }

    private function normalizePhone(?string $value): ?string
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            return null;
        }

        return (str_starts_with($raw, '+') ? '+' : '').$digits;
    }

    private function normalizeText(?string $value): ?string
    {
        $normalized = Str::squish((string) $value);

        return $normalized === '' ? null : $normalized;
    }
};
