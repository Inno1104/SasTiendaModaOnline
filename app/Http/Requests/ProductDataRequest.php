<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ProductoImagen;
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
            'imagenes' => ['nullable', 'array', 'max:500'],
            'imagenes.*.archivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'imagenes.*.talla' => ['nullable', 'string', 'max:50'],
            'imagenes.*.color' => ['nullable', 'string', 'max:80'],
            'imagenes_existentes' => ['sometimes', 'array', 'max:500'],
            'imagenes_existentes.*.id' => ['required', 'integer'],
            'imagenes_existentes.*.archivo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=6000,max_height=6000'],
            'imagenes_existentes.*.eliminar' => ['nullable', 'boolean'],
            'activo' => ['required', 'boolean'],
            'destacado' => ['required', 'boolean'],
            'variantes' => ['sometimes', 'array', 'max:100'],
            'variantes.*.talla' => ['required', 'string', 'max:50'],
            'variantes.*.detalle_talla' => ['nullable', 'string', 'max:500'],
            'variantes.*.color' => ['required', 'string', 'max:80'],
            'variantes.*.codigo_barra' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('producto_variantes', 'codigo_barra')->where('tienda_id', $storeId),
            ],
            'variantes.*.stock' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'variantes.*.stock_minimo' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'variantes_existentes' => ['sometimes', 'array', 'max:100'],
            'variantes_existentes.*.id' => ['required', 'integer'],
            'variantes_existentes.*.talla' => ['required', 'string', 'max:50'],
            'variantes_existentes.*.detalle_talla' => ['nullable', 'string', 'max:500'],
            'variantes_existentes.*.color' => ['required', 'string', 'max:80'],
            'variantes_existentes.*.codigo_barra' => ['nullable', 'string', 'max:100'],
            'variantes_existentes.*.precio_compra' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'variantes_existentes.*.precio_venta' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'variantes_existentes.*.precio_oferta' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'variantes_existentes.*.stock' => ['sometimes', 'required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'variantes_existentes.*.stock_minimo' => ['required', 'integer', 'min:'.ProductoVariante::MIN_STOCK, 'max:'.ProductoVariante::MAX_STOCK],
            'variantes_existentes.*.motivo_stock' => ['nullable', 'string', 'max:255'],
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
        $normalized['variantes_existentes'] = $this->normalizedExistingVariants();
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

            $product = $this->route('producto');
            if ($product instanceof Producto) {
                $productImageIds = ProductoImagen::withoutGlobalScopes()
                    ->where('tienda_id', $this->user()->tienda_id)
                    ->where('producto_id', $product->id)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);
                foreach ((array) $this->input('imagenes_existentes', []) as $index => $existingImage) {
                    if (! $productImageIds->contains((int) ($existingImage['id'] ?? 0))) {
                        $validator->errors()->add("imagenes_existentes.$index.id", 'La imagen no pertenece a este producto.');
                    }
                }
                $existing = ProductoVariante::withoutGlobalScopes()
                    ->where('tienda_id', $this->user()->tienda_id)
                    ->where('producto_id', $product->id)
                    ->get(['id', 'talla', 'color', 'codigo_barra', 'precio_compra', 'precio_venta', 'precio_oferta', 'stock'])
                    ->keyBy('id');
                $finalCombinations = [];
                $finalBarcodes = ProductoVariante::withoutGlobalScopes()
                    ->where('tienda_id', $this->user()->tienda_id)
                    ->where('producto_id', '!=', $product->id)
                    ->whereNotNull('codigo_barra')
                    ->pluck('codigo_barra')
                    ->mapWithKeys(fn ($barcode) => [mb_strtoupper(trim((string) $barcode)) => true])
                    ->all();
                $submittedIds = collect((array) $this->input('variantes_existentes', []))->pluck('id')->map(fn ($id) => (int) $id);
                foreach ($existing->except($submittedIds->all()) as $variant) {
                    $finalCombinations[mb_strtolower(trim($variant->talla).'|'.trim($variant->color))] = true;
                    $barcode = mb_strtoupper(trim((string) $variant->codigo_barra));
                    if ($barcode !== '') {
                        $finalBarcodes[$barcode] = true;
                    }
                }
                foreach ((array) $this->input('variantes_existentes', []) as $index => $variant) {
                    $id = (int) ($variant['id'] ?? 0);
                    if (! $existing->has($id)) {
                        $validator->errors()->add("variantes_existentes.$index.id", 'La variante no pertenece a este producto.');

                        continue;
                    }
                    $key = mb_strtolower(trim((string) ($variant['talla'] ?? '')).'|'.trim((string) ($variant['color'] ?? '')));
                    if (isset($finalCombinations[$key])) {
                        $validator->errors()->add("variantes_existentes.$index.talla", 'La combinación de talla y color está repetida.');
                    }
                    $finalCombinations[$key] = true;
                    $barcode = mb_strtoupper(trim((string) ($variant['codigo_barra'] ?? '')));
                    if ($barcode !== '' && isset($finalBarcodes[$barcode])) {
                        $validator->errors()->add("variantes_existentes.$index.codigo_barra", 'El código de barras está repetido.');
                    }
                    if ($barcode !== '') {
                        $finalBarcodes[$barcode] = true;
                    }
                    $current = $existing->get($id);
                    if ($current && array_key_exists('stock', $variant) && (int) $variant['stock'] !== (int) $current->stock && trim((string) ($variant['motivo_stock'] ?? '')) === '') {
                        $validator->errors()->add("variantes_existentes.$index.motivo_stock", 'Indica el motivo del ajuste de stock.');
                    }
                    $salePrice = ($variant['precio_venta'] ?? null) !== null ? (float) $variant['precio_venta'] : (float) $product->precio_venta;
                    if (($variant['precio_oferta'] ?? null) !== null && (float) $variant['precio_oferta'] >= $salePrice) {
                        $validator->errors()->add("variantes_existentes.$index.precio_oferta", 'La oferta debe ser menor al precio de venta aplicable.');
                    }
                }
                foreach ((array) $this->input('variantes', []) as $index => $variant) {
                    $key = mb_strtolower(trim((string) ($variant['talla'] ?? '')).'|'.trim((string) ($variant['color'] ?? '')));
                    if ($key !== '|' && isset($finalCombinations[$key])) {
                        $validator->errors()->add("variantes.$index.talla", 'Esta combinación de talla y color ya existe en el producto.');
                    }
                    $finalCombinations[$key] = true;
                }
            }

            $variants = collect((array) $this->input('variantes', []));
            if ($product instanceof Producto) {
                $variants = $variants->concat(ProductoVariante::withoutGlobalScopes()
                    ->where('tienda_id', $this->user()->tienda_id)
                    ->where('producto_id', $product->id)
                    ->get(['talla', 'color'])
                    ->map(fn (ProductoVariante $variant) => ['talla' => $variant->talla, 'color' => $variant->color]));
            }
            foreach ((array) $this->input('imagenes', []) as $index => $image) {
                $size = trim((string) ($image['talla'] ?? ''));
                $color = trim((string) ($image['color'] ?? ''));
                if (($size !== '' || $color !== '') && ! $this->hasFile("imagenes.$index.archivo")) {
                    $validator->errors()->add("imagenes.$index.archivo", 'Selecciona una imagen antes de asignar talla o color.');

                    continue;
                }
                if ($size !== '' && ! $variants->contains(fn ($variant) => ($variant['talla'] ?? null) === $size)) {
                    $validator->errors()->add("imagenes.$index.talla", 'La talla seleccionada no existe en las variantes del producto.');
                }
                if ($color !== '' && ! $variants->contains(fn ($variant) => ($variant['color'] ?? null) === $color)) {
                    $validator->errors()->add("imagenes.$index.color", 'El color seleccionado no existe en las variantes del producto.');
                }
                if ($size !== '' && $color !== '' && ! $variants->contains(fn ($variant) => ($variant['talla'] ?? null) === $size && ($variant['color'] ?? null) === $color)) {
                    $validator->errors()->add("imagenes.$index.color", 'No existe una variante con esa combinación de talla y color.');
                }
            }
            collect((array) $this->input('imagenes', []))->filter(fn ($image, $index) => $this->hasFile("imagenes.$index.archivo"))
                ->groupBy(fn ($image) => mb_strtolower(trim((string) ($image['talla'] ?? '')).'|'.trim((string) ($image['color'] ?? ''))))
                ->each(function ($images) use ($validator, $product): void {
                    $first = $images->first();
                    $existingCount = $product instanceof Producto
                        ? ProductoImagen::withoutGlobalScopes()
                            ->where('tienda_id', $this->user()->tienda_id)
                            ->where('producto_id', $product->id)
                            ->where('talla', ($value = trim((string) ($first['talla'] ?? ''))) === '' ? null : $value)
                            ->where('color', ($value = trim((string) ($first['color'] ?? ''))) === '' ? null : $value)
                            ->count()
                        : 0;
                    if ($existingCount + $images->count() > 5) {
                        $validator->errors()->add('imagenes', 'Cada combinación de talla y color admite máximo cinco imágenes en total.');
                    }
                });
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
            ->flatMap(function ($variant): array {
                $variant = is_array($variant) ? $variant : [];
                $sizes = collect(preg_split('/[,;|]+/u', (string) ($variant['talla'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn (string $size) => trim($size))->filter()
                    ->unique(fn (string $size) => mb_strtolower($size))->values();
                if ($sizes->isEmpty()) {
                    $sizes->push('');
                }

                return $sizes->map(fn (string $size): array => [
                    'talla' => $size,
                    'detalle_talla' => ($detail = trim((string) ($variant['detalle_talla'] ?? ''))) === '' ? null : $detail,
                    'color' => trim((string) ($variant['color'] ?? '')),
                    'codigo_barra' => ($barcode = mb_strtoupper(trim((string) ($variant['codigo_barra'] ?? '')))) === '' ? null : $barcode,
                    'stock' => $variant['stock'] ?? 0,
                    'stock_minimo' => $variant['stock_minimo'] ?? 5,
                ])->all();
            })
            ->reject(fn (array $variant) => $variant['talla'] === '' && $variant['color'] === '' && $variant['codigo_barra'] === null)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function normalizedExistingVariants(): array
    {
        return collect((array) $this->input('variantes_existentes', []))->map(function ($variant): array {
            $variant = is_array($variant) ? $variant : [];

            $normalized = [
                'id' => (int) ($variant['id'] ?? 0),
                'talla' => trim((string) ($variant['talla'] ?? '')),
                'detalle_talla' => ($detail = trim((string) ($variant['detalle_talla'] ?? ''))) === '' ? null : $detail,
                'color' => trim((string) ($variant['color'] ?? '')),
                'codigo_barra' => ($barcode = mb_strtoupper(trim((string) ($variant['codigo_barra'] ?? '')))) === '' ? null : $barcode,
                'precio_compra' => ($variant['precio_compra'] ?? '') === '' ? null : $variant['precio_compra'],
                'precio_venta' => ($variant['precio_venta'] ?? '') === '' ? null : $variant['precio_venta'],
                'precio_oferta' => ($variant['precio_oferta'] ?? '') === '' ? null : $variant['precio_oferta'],
                'stock_minimo' => $variant['stock_minimo'] ?? 0,
                'motivo_stock' => trim((string) ($variant['motivo_stock'] ?? '')),
            ];
            if (array_key_exists('stock', $variant)) {
                $normalized['stock'] = $variant['stock'];
            }

            return $normalized;
        })->values()->all();
    }
}
