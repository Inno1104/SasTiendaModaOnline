<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoVariante;
use App\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class ProductDataRequest extends FormRequest
{
    /** @return array<string, mixed> */
    protected function productRules(): array
    {
        $storeId = (int) $this->user()->tienda_id;

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->where('tienda_id', $storeId)],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')->where('tienda_id', $storeId)],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->where('tienda_id', $storeId)],
            'genero' => ['required', Rule::in(['damas', 'caballeros', 'ninos', 'unisex'])],
            'tipo' => ['required', Rule::in(['casual', 'deportivo', 'formal', 'otro'])],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'precio_compra' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'precio_venta' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'precio_oferta' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'lt:precio_venta', 'max:99999999.99'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'activo' => ['required', 'boolean'],
            'destacado' => ['required', 'boolean'],
            'variantes' => ['sometimes', 'array', 'max:100'],
            'variantes.*.talla' => ['required', 'string', 'max:50'],
            'variantes.*.color' => ['required', 'string', 'max:80'],
            'variantes.*.codigo_barra' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('producto_variantes', 'codigo_barra')->where('tienda_id', $storeId),
            ],
            'variantes.*.stock' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'variantes.*.stock_minimo' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['nombre', 'descripcion'] as $field) {
            if ($this->exists($field)) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = $value === '' ? null : $value;
            }
        }

        foreach (['marca_id', 'proveedor_id', 'precio_oferta'] as $field) {
            if ($this->input($field) === '') {
                $normalized[$field] = null;
            }
        }
        if ($this->input('precio_compra') === '' || $this->input('precio_compra') === null) {
            $normalized['precio_compra'] = 0;
        }

        $normalized['activo'] = $this->normalizedBoolean('activo');
        $normalized['destacado'] = $this->normalizedBoolean('destacado');
        $normalized['variantes'] = $this->normalizedVariants();
        $this->merge($normalized);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateCategoryState($validator);
            $this->validateBrandState($validator);
            $this->validateSupplierState($validator);

            $seenVariants = [];
            $seenBarcodes = [];

            foreach ((array) $this->input('variantes', []) as $index => $variant) {
                $variantKey = mb_strtolower(
                    trim((string) ($variant['talla'] ?? '')).'|'.trim((string) ($variant['color'] ?? ''))
                );
                if ($variantKey !== '|' && isset($seenVariants[$variantKey])) {
                    $validator->errors()->add("variantes.$index.talla", 'La combinación de talla y color está repetida.');
                }
                $seenVariants[$variantKey] = true;

                $barcode = mb_strtoupper(trim((string) ($variant['codigo_barra'] ?? '')));
                if ($barcode !== '' && isset($seenBarcodes[$barcode])) {
                    $validator->errors()->add("variantes.$index.codigo_barra", 'El código de barras está repetido.');
                }
                $seenBarcodes[$barcode] = true;
            }
        });
    }

    private function validateCategoryState(Validator $validator): void
    {
        if ($validator->errors()->has('categoria_id')) {
            return;
        }

        $category = Categoria::withoutGlobalScopes()
            ->where('tienda_id', $this->user()->tienda_id)
            ->find($this->integer('categoria_id'));

        if (! $category || $category->activo) {
            return;
        }

        $product = $this->route('producto');
        $canKeepInactiveCategory = $product instanceof Producto
            && (int) $product->categoria_id === (int) $category->id
            && ! $this->boolean('activo');

        if (! $canKeepInactiveCategory) {
            $validator->errors()->add('categoria_id', 'Selecciona una categoría activa para el producto.');
        }
    }

    private function validateBrandState(Validator $validator): void
    {
        if ($validator->errors()->has('marca_id') || $this->input('marca_id') === null) {
            return;
        }

        $brand = Marca::withoutGlobalScopes()
            ->where('tienda_id', $this->user()->tienda_id)
            ->find($this->integer('marca_id'));

        if (! $brand || $brand->activo) {
            return;
        }

        $product = $this->route('producto');
        $canKeepCurrentBrand = $product instanceof Producto
            && (int) $product->marca_id === (int) $brand->id;

        if (! $canKeepCurrentBrand) {
            $validator->errors()->add('marca_id', 'Selecciona una marca activa para el producto.');
        }
    }

    private function validateSupplierState(Validator $validator): void
    {
        if ($validator->errors()->has('proveedor_id') || $this->input('proveedor_id') === null) {
            return;
        }

        $supplier = Proveedor::withoutGlobalScopes()
            ->where('tienda_id', $this->user()->tienda_id)
            ->find($this->integer('proveedor_id'));

        if (! $supplier || $supplier->activo) {
            return;
        }

        $product = $this->route('producto');
        $canKeepCurrentSupplier = $product instanceof Producto
            && (int) $product->proveedor_id === (int) $supplier->id;

        if (! $canKeepCurrentSupplier) {
            $validator->errors()->add('proveedor_id', 'Selecciona un proveedor activo para el producto.');
        }
    }

    protected function defaultActiveState(): bool
    {
        return false;
    }

    private function normalizedBoolean(string $field): bool
    {
        return $this->exists($field) ? $this->boolean($field) : ($field === 'activo' && $this->defaultActiveState());
    }

    /** @return list<array<string, mixed>> */
    private function normalizedVariants(): array
    {
        return collect((array) $this->input('variantes', []))
            ->map(function ($variant): array {
                $variant = is_array($variant) ? $variant : [];

                return [
                    'talla' => trim((string) ($variant['talla'] ?? '')),
                    'color' => trim((string) ($variant['color'] ?? '')),
                    'codigo_barra' => ($barcode = mb_strtoupper(trim((string) ($variant['codigo_barra'] ?? '')))) === '' ? null : $barcode,
                    'stock' => $variant['stock'] ?? 0,
                    'stock_minimo' => $variant['stock_minimo'] ?? 5,
                ];
            })
            ->reject(fn (array $variant) => $variant['talla'] === '' && $variant['color'] === '' && $variant['codigo_barra'] === null)
            ->values()
            ->all();
    }
}
