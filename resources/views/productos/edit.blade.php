@extends('layouts.app')
@section('title','Editar Producto')
@section('page-title','Editar Producto')
@push('styles')<link rel="stylesheet" href="{{ asset('css/product-form.css') }}"><link rel="stylesheet" href="{{ asset('css/product-size-detail.css') }}">@endpush

@section('content')
@php
    $newVariants = old('variantes', [['talla' => '', 'detalle_talla' => '', 'color' => '', 'codigo_barra' => '', 'stock' => 0, 'stock_minimo' => 5]]);
@endphp
<div class="page-header">
    <div><h1>Editar: {{ $producto->nombre }}</h1></div>
    <a href="{{ route('productos.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<form id="productForm" method="POST" action="{{ route('productos.update',$producto) }}" enctype="multipart/form-data" novalidate>
    @csrf @method('PUT')
    <div class="product-edit-layout">
        <div class="product-edit-column">
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><div class="card-title">Información del Producto</div></div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" value="{{ old('nombre',$producto->nombre) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoría *</label>
                        <select name="categoria_id" class="form-control" required>
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->id }}" {{ $producto->categoria_id == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Marca</label>
                        <select name="marca_id" class="form-control">
                            <option value="">Sin marca</option>
                            @foreach($marcas as $m)
                                <option value="{{ $m->id }}" @selected((string) old('marca_id', $producto->marca_id) === (string) $m->id)>{{ $m->nombre }}{{ $m->activo ? '' : ' (Inactiva)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor_id" class="form-control">
                            <option value="">Sin proveedor</option>
                            @foreach($proveedores as $pv)
                                <option value="{{ $pv->id }}" {{ $producto->proveedor_id == $pv->id ? 'selected' : '' }}>{{ $pv->nombre }}{{ $pv->activo ? '' : ' (Inactivo)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Género</label>
                        <select name="genero" class="form-control">
                            @foreach(['unisex','damas','caballeros','ninos'] as $g)
                                <option value="{{ $g }}" {{ $producto->genero == $g ? 'selected' : '' }}>{{ ucfirst($g) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-control">
                            @foreach(['casual','deportivo','formal','otro'] as $t)
                                <option value="{{ $t }}" {{ $producto->tipo == $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3">{{ old('descripcion',$producto->descripcion) }}</textarea>
                </div>
            </div>

            <section class="card product-section variants-section">
                <header class="product-section-header variants-header"><span class="section-icon"><i class="fa-solid fa-layer-group"></i></span><div><h2>Tallas, colores y stock</h2><p>Consulta las combinaciones actuales y agrega nuevas variantes sin modificar su historial.</p></div><button type="button" class="btn btn-outline btn-sm" id="addVariant"><i class="fa-solid fa-plus"></i> Agregar variante</button></header>

                <div class="existing-variants-heading">
                    <h3 class="existing-variants-title">Variantes existentes</h3>
                    @if($producto->variantes->isNotEmpty())
                        <div class="existing-variant-filters" data-existing-variant-filters>
                            <label><span>Filtrar por talla</span><select class="form-control" data-existing-size-filter><option value="">Todas las tallas</option>@foreach($producto->variantes->pluck('talla')->filter()->unique(fn ($value) => mb_strtolower(trim($value)))->sort() as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></label>
                            <label><span>Filtrar por color</span><select class="form-control" data-existing-color-filter><option value="">Todos los colores</option>@foreach($producto->variantes->pluck('color')->filter()->unique(fn ($value) => mb_strtolower(trim($value)))->sort() as $color)<option value="{{ $color }}">{{ $color }}</option>@endforeach</select></label>
                            <button type="button" class="existing-filter-clear" data-clear-existing-filters hidden><i class="fa-solid fa-rotate-left"></i> Limpiar</button>
                        </div>
                    @endif
                </div>
                <p class="existing-filter-result" data-existing-filter-result aria-live="polite"></p>
                <div class="existing-variants-list" data-existing-variants-list>
                    @forelse($producto->variantes as $variantIndex => $variant)
                        <div class="existing-variant-row" data-gallery-existing-variant data-size="{{ $variant->talla }}" data-color="{{ $variant->color }}">
                            <input type="hidden" name="variantes_existentes[{{ $variantIndex }}][id]" value="{{ $variant->id }}">
                            <label><small>Talla</small><input class="form-control" name="variantes_existentes[{{ $variantIndex }}][talla]" value="{{ old("variantes_existentes.$variantIndex.talla", $variant->talla) }}" maxlength="50" required data-existing-size></label>
                            <label><small>Detalle de talla</small><input class="form-control" name="variantes_existentes[{{ $variantIndex }}][detalle_talla]" value="{{ old("variantes_existentes.$variantIndex.detalle_talla", $variant->detalle_talla) }}" maxlength="500" placeholder="Ej.: Pecho 84-88 cm"></label>
                            <label><small>Color</small><input class="form-control" name="variantes_existentes[{{ $variantIndex }}][color]" value="{{ old("variantes_existentes.$variantIndex.color", $variant->color) }}" maxlength="80" required data-existing-color></label>
                            <label><small>Código de barras</small><input class="form-control" name="variantes_existentes[{{ $variantIndex }}][codigo_barra]" value="{{ old("variantes_existentes.$variantIndex.codigo_barra", $variant->codigo_barra) }}" maxlength="100" placeholder="Opcional"></label>
                            <label><small>Stock actual</small><input type="number" class="form-control" name="variantes_existentes[{{ $variantIndex }}][stock]" value="{{ old("variantes_existentes.$variantIndex.stock", $variant->stock) }}" min="0" max="2000000000" required data-existing-stock data-original-stock="{{ $variant->stock }}"></label>
                            <label><small>Stock mínimo</small><input type="number" class="form-control" name="variantes_existentes[{{ $variantIndex }}][stock_minimo]" value="{{ old("variantes_existentes.$variantIndex.stock_minimo", $variant->stock_minimo) }}" min="0" max="2000000000" required></label>
                            <label><small>Precio compra</small><input type="number" step="0.01" class="form-control" name="variantes_existentes[{{ $variantIndex }}][precio_compra]" value="{{ old("variantes_existentes.$variantIndex.precio_compra", $variant->precio_compra) }}" min="0" placeholder="General: {{ $producto->precio_compra }}"></label>
                            <label><small>Precio venta</small><input type="number" step="0.01" class="form-control" name="variantes_existentes[{{ $variantIndex }}][precio_venta]" value="{{ old("variantes_existentes.$variantIndex.precio_venta", $variant->precio_venta) }}" min="0.01" placeholder="General: {{ $producto->precio_venta }}"></label>
                            <label><small>Precio oferta</small><input type="number" step="0.01" class="form-control" name="variantes_existentes[{{ $variantIndex }}][precio_oferta]" value="{{ old("variantes_existentes.$variantIndex.precio_oferta", $variant->precio_oferta) }}" min="0.01" placeholder="Opcional"></label>
                            <label class="existing-stock-reason"><small>Motivo del ajuste</small><input class="form-control" name="variantes_existentes[{{ $variantIndex }}][motivo_stock]" value="{{ old("variantes_existentes.$variantIndex.motivo_stock") }}" maxlength="255" placeholder="Obligatorio si cambia el stock" data-stock-reason></label>
                            <span class="variant-state {{ $variant->estaAgotada() ? 'out' : ($variant->tieneStockBajo() ? 'low' : '') }}">{{ $variant->descripcionStock() }}</span>
                        </div>
                    @empty
                        <p class="empty-variants">Este producto todavía no tiene variantes.</p>
                    @endforelse
                    <p class="empty-variants" data-existing-filter-empty hidden>No existen variantes con esa talla y color.</p>
                </div>
                <nav class="existing-variant-pagination" data-existing-pagination aria-label="Paginación de variantes" hidden>
                    <button type="button" data-existing-page-prev aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></button>
                    <div data-existing-page-numbers></div>
                    <button type="button" data-existing-page-next aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                </nav>
                @foreach($errors->get('variantes_existentes.*') as $messages)@foreach($messages as $message)<small class="field-error">{{ $message }}</small>@endforeach @endforeach

                <h3 class="existing-variants-title new-variants-title">Nuevas variantes</h3>
                <p class="new-variants-help">El stock indicado se registrará como entrada inicial en el kardex.</p>
                <div class="variant-summary" aria-live="polite"><span><i class="fa-solid fa-boxes-stacked"></i><strong id="variantCount">0</strong> nuevas</span><span><i class="fa-solid fa-cubes"></i><strong id="totalStock">0</strong> unidades</span><span class="summary-warning"><i class="fa-solid fa-triangle-exclamation"></i><strong id="lowStockCount">0</strong> con stock bajo</span></div>
                <div class="variant-table-header"><span>Talla</span><span>Detalle de talla</span><span>Color</span><span>Código de barras</span><span>Stock inicial</span><span>Mínimo</span><span>Estado</span><span></span></div>
                <div id="variantsContainer">
                    @foreach($newVariants as $index => $variant)
                        <div class="variant-row" data-variant-row>
                            <label><span>Talla(s)</span><input type="text" name="variantes[{{ $index }}][talla]" class="form-control" value="{{ $variant['talla'] ?? '' }}" maxlength="50" placeholder="S, M, L, XL" title="Separa varias tallas con comas" data-field="talla" data-variant-size></label>
                            <label><span>Detalle de talla</span><input type="text" name="variantes[{{ $index }}][detalle_talla]" class="form-control" value="{{ $variant['detalle_talla'] ?? '' }}" maxlength="500" placeholder="Ej.: Pecho 84-88 cm" data-field="detalle_talla"></label>
                            <label><span>Color</span><input type="text" name="variantes[{{ $index }}][color]" class="form-control" value="{{ $variant['color'] ?? '' }}" maxlength="80" placeholder="Negro" data-field="color" data-variant-color></label>
                            <label><span>Código de barras</span><input type="text" name="variantes[{{ $index }}][codigo_barra]" class="form-control" value="{{ $variant['codigo_barra'] ?? '' }}" maxlength="100" placeholder="Opcional" data-field="codigo_barra" data-variant-barcode></label>
                            <label><span>Stock inicial</span><input type="number" name="variantes[{{ $index }}][stock]" class="form-control" value="{{ $variant['stock'] ?? 0 }}" min="0" max="2000000000" data-field="stock" data-variant-stock></label>
                            <label><span>Mínimo</span><input type="number" name="variantes[{{ $index }}][stock_minimo]" class="form-control" value="{{ $variant['stock_minimo'] ?? 5 }}" min="0" max="2000000000" data-field="stock_minimo" data-variant-minimum></label>
                            <span class="variant-state" data-variant-state>Disponible</span><button type="button" class="remove-variant" data-remove-variant aria-label="Eliminar nueva variante"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    @endforeach
                </div>
                <div id="variantDuplicateError" class="variant-duplicate-error" role="alert" hidden></div>
                @error('variantes')<small class="field-error">{{ $message }}</small>@enderror
                @foreach($errors->get('variantes.*') as $messages)@foreach($messages as $message)<small class="field-error">{{ $message }}</small>@endforeach @endforeach
            </section>
        </div>

        <div class="product-edit-column">
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><div class="card-title">Precios</div></div>
                <div class="form-group">
                    <label class="form-label">Precio Compra</label>
                    <input type="number" name="precio_compra" class="form-control" value="{{ old('precio_compra',$producto->precio_compra) }}" step="0.01">
                </div>
                <div class="form-group">
                    <label class="form-label">Precio Venta *</label>
                    <input type="number" name="precio_venta" class="form-control" value="{{ old('precio_venta',$producto->precio_venta) }}" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Precio Oferta</label>
                    <input type="number" name="precio_oferta" class="form-control" value="{{ old('precio_oferta',$producto->precio_oferta) }}" step="0.01">
                </div>
            </div>
            <section class="card product-section product-images-section" style="margin-bottom:20px;"><x-product-section-title icon="fa-images" title="Agregar fotos por variante" help="Selecciona una variante existente o nueva y carga hasta completar cinco imágenes." />
                @if($producto->imagenes->isNotEmpty())
                    <div class="existing-product-images" aria-label="Imágenes actuales">
                        @foreach($producto->imagenes as $imageIndex => $currentImage)
                            <figure data-existing-image>
                                <input type="hidden" name="imagenes_existentes[{{ $imageIndex }}][id]" value="{{ $currentImage->id }}">
                                <button type="button" class="existing-image-preview" data-existing-image-picker title="Reemplazar imagen">
                                    <img src="{{ asset('storage/'.$currentImage->ruta) }}" alt="{{ $producto->nombre }} · {{ $currentImage->talla }} / {{ $currentImage->color }}">
                                    <span><i class="fa-solid fa-camera"></i> Cambiar</span>
                                </button>
                                <input type="file" name="imagenes_existentes[{{ $imageIndex }}][archivo]" accept="image/jpeg,image/png,image/webp" hidden data-existing-image-input>
                                <figcaption>{{ $currentImage->talla ?: 'Todas' }} / {{ $currentImage->color ?: 'Todos' }}</figcaption>
                                <label class="existing-image-delete"><input type="checkbox" name="imagenes_existentes[{{ $imageIndex }}][eliminar]" value="1" data-existing-image-delete> Eliminar</label>
                            </figure>
                        @endforeach
                    </div>
                    <p class="existing-images-help"><i class="fa-solid fa-circle-info"></i> Pulsa una fotografía para reemplazarla o marca “Eliminar”. Los cambios se aplican al actualizar el producto.</p>
                @endif
                <div data-product-gallery>
                    <section class="product-gallery-group" data-gallery-group>
                        <div class="product-gallery-group-assignment"><label>Variante de las fotos<select data-gallery-group-variant><option value="">Seleccionar talla y color</option></select></label><small>Las fotografías se asociarán a la combinación seleccionada.</small></div>
                        <div class="product-gallery-grid">
                            @for($imageIndex = 0; $imageIndex < 5; $imageIndex++)
                                <article class="product-gallery-item" data-gallery-item>
                                    <button type="button" class="product-gallery-preview" data-gallery-picker aria-label="Seleccionar {{ $imageIndex === 0 ? 'imagen principal' : 'imagen alterna '.($imageIndex + 1) }}"><span><i class="{{ $imageIndex === 0 ? 'fa-solid fa-cloud-arrow-up' : 'fa-regular fa-image' }}"></i><strong>{{ $imageIndex === 0 ? 'Nueva imagen principal' : 'Alterna '.$imageIndex }}</strong><small>Haz clic para cargar</small></span><img alt="Vista previa" hidden>@if($imageIndex === 0)<b class="gallery-primary-badge">Nueva</b>@endif</button>
                                    <input type="file" name="imagenes[{{ $imageIndex }}][archivo]" accept="image/jpeg,image/png,image/webp" hidden data-gallery-input><input type="hidden" name="imagenes[{{ $imageIndex }}][talla]" data-gallery-size><input type="hidden" name="imagenes[{{ $imageIndex }}][color]" data-gallery-color>
                                </article>
                            @endfor
                        </div>
                    </section>
                </div>
                <button type="button" class="btn btn-outline image-select-button" data-add-gallery-group><i class="fa-solid fa-plus"></i> Agregar fotos para otra variante</button>
                <small id="imageClientError" class="field-error" hidden></small>@error('imagenes')<small class="field-error">{{ $message }}</small>@enderror
            </section>
            <div class="card">
                <div class="card-header"><div class="card-title">Opciones</div></div>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;cursor:pointer;">
                    <input type="checkbox" name="activo" value="1" {{ $producto->activo ? 'checked' : '' }} style="accent-color:var(--accent);width:16px;height:16px;">
                    Producto activo
                </label>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                    <input type="checkbox" name="destacado" value="1" {{ $producto->destacado ? 'checked' : '' }} style="accent-color:var(--accent);width:16px;height:16px;">
                    Producto destacado
                </label>
            </div>
        </div>
    </div>
    <div style="margin-top:20px;display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('productos.index') }}" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Actualizar</button>
    </div>
</form>

<template id="variantTemplate"><div class="variant-row" data-variant-row>
    <label><span>Talla</span><input type="text" class="form-control" maxlength="50" placeholder="M" data-field="talla" data-variant-size></label><label><span>Detalle de talla</span><input type="text" class="form-control" maxlength="500" placeholder="Ej.: Pecho 84-88 cm" data-field="detalle_talla"></label><label><span>Color</span><input type="text" class="form-control" maxlength="80" placeholder="Negro" data-field="color" data-variant-color></label><label><span>Código de barras</span><input type="text" class="form-control" maxlength="100" placeholder="Opcional" data-field="codigo_barra" data-variant-barcode></label><label><span>Stock inicial</span><input type="number" class="form-control" value="0" min="0" max="2000000000" data-field="stock" data-variant-stock></label><label><span>Mínimo</span><input type="number" class="form-control" value="5" min="0" max="2000000000" data-field="stock_minimo" data-variant-minimum></label><span class="variant-state out" data-variant-state>Agotada</span><button type="button" class="remove-variant" data-remove-variant aria-label="Eliminar nueva variante"><i class="fa-solid fa-trash"></i></button>
</div></template>
@endsection

@push('scripts')
<script src="{{ asset('js/product-image.js') }}"></script>
<script src="{{ asset('js/product-form.js') }}" defer></script>
@endpush
