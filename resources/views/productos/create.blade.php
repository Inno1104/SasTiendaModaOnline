@extends('layouts.app')
@section('title', 'Nuevo Producto')
@section('page-title', 'Nuevo Producto')
@push('styles')<link rel="stylesheet" href="{{ asset('css/product-form.css') }}">@endpush

@section('content')
@php
    $oldVariants = old('variantes', [['talla' => '', 'color' => '', 'codigo_barra' => '', 'stock' => 0, 'stock_minimo' => 5]]);
@endphp
<div class="page-header product-page-header">
    <div><h1>Nuevo producto</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span>›</span><a href="{{ route('productos.index') }}">Productos</a><span>›</span>Nuevo</div><p>Registra la información comercial y el stock independiente de cada talla y color.</p></div>
    <a href="{{ route('productos.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>
@if($errors->any())<div class="product-form-alert" role="alert"><i class="fa-solid fa-circle-exclamation"></i><div><strong>Revisa la información ingresada.</strong><span>{{ $errors->first() }}</span></div></div>@endif

<form id="productForm" method="POST" action="{{ route('productos.store') }}" enctype="multipart/form-data" novalidate>
@csrf
<div class="product-form-layout">
<div class="product-form-main">
    <section class="card product-section">
        <x-product-section-title icon="fa-shirt" title="Información del producto" help="Datos que identificarán la prenda en el catálogo." />
        <div class="product-field-grid">
            <label class="field-group"><span>Nombre del producto <b>*</b></span><input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" maxlength="255" required autofocus>@error('nombre')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Categoría <b>*</b></span><select name="categoria_id" class="form-control" required><option value="">Seleccionar categoría</option>@foreach($categorias as $cat)<option value="{{ $cat->id }}" @selected((string) old('categoria_id') === (string) $cat->id)>{{ $cat->nombre }}</option>@endforeach</select>@error('categoria_id')<small class="field-error">{{ $message }}</small>@enderror</label>
            <label class="field-group"><span>Marca</span><select name="marca_id" class="form-control"><option value="">Sin marca</option>@foreach($marcas as $item)<option value="{{ $item->id }}" @selected((string) old('marca_id') === (string) $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
            <label class="field-group"><span>Proveedor</span><select name="proveedor_id" class="form-control"><option value="">Sin proveedor</option>@foreach($proveedores as $item)<option value="{{ $item->id }}" @selected((string) old('proveedor_id') === (string) $item->id)>{{ $item->nombre }}</option>@endforeach</select></label>
            <label class="field-group"><span>Género <b>*</b></span><select name="genero" class="form-control" required>@foreach(['unisex'=>'Unisex','damas'=>'Damas','caballeros'=>'Caballeros','ninos'=>'Niños'] as $value=>$label)<option value="{{ $value }}" @selected(old('genero','unisex')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Tipo <b>*</b></span><select name="tipo" class="form-control" required>@foreach(['casual'=>'Casual','deportivo'=>'Deportivo','formal'=>'Formal','otro'=>'Otro'] as $value=>$label)<option value="{{ $value }}" @selected(old('tipo','casual')===$value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group field-wide"><span>Descripción</span><textarea name="descripcion" class="form-control" rows="3" maxlength="2000" placeholder="Material, corte, colección u otros detalles...">{{ old('descripcion') }}</textarea></label>
        </div>
    </section>

    <section class="card product-section variants-section">
        <header class="product-section-header variants-header"><span class="section-icon"><i class="fa-solid fa-layer-group"></i></span><div><h2>Tallas, colores y stock</h2><p>Cada fila representa una combinación con inventario independiente.</p></div><button type="button" class="btn btn-outline btn-sm" id="addVariant"><i class="fa-solid fa-plus"></i> Agregar variante</button></header>
        <div class="variant-summary" aria-live="polite"><span><i class="fa-solid fa-boxes-stacked"></i><strong id="variantCount">0</strong> variantes</span><span><i class="fa-solid fa-cubes"></i><strong id="totalStock">0</strong> unidades</span><span class="summary-warning"><i class="fa-solid fa-triangle-exclamation"></i><strong id="lowStockCount">0</strong> con stock bajo</span></div>
        <div class="variant-table-header"><span>Talla</span><span>Color</span><span>Código de barras</span><span>Stock</span><span>Mínimo</span><span>Estado</span><span></span></div>
        <div id="variantsContainer">
        @foreach($oldVariants as $index=>$variant)
            <div class="variant-row" data-variant-row>
                <label><span>Talla</span><input type="text" name="variantes[{{ $index }}][talla]" class="form-control" value="{{ $variant['talla']??'' }}" maxlength="50" placeholder="M" data-field="talla" data-variant-size></label>
                <label><span>Color</span><input type="text" name="variantes[{{ $index }}][color]" class="form-control" value="{{ $variant['color']??'' }}" maxlength="80" placeholder="Negro" data-field="color" data-variant-color></label>
                <label><span>Código de barras</span><input type="text" name="variantes[{{ $index }}][codigo_barra]" class="form-control" value="{{ $variant['codigo_barra']??'' }}" maxlength="100" placeholder="Opcional" data-field="codigo_barra" data-variant-barcode></label>
                <label><span>Stock</span><input type="number" name="variantes[{{ $index }}][stock]" class="form-control" value="{{ $variant['stock']??0 }}" min="0" max="2000000000" data-field="stock" data-variant-stock></label>
                <label><span>Mínimo</span><input type="number" name="variantes[{{ $index }}][stock_minimo]" class="form-control" value="{{ $variant['stock_minimo']??5 }}" min="0" max="2000000000" data-field="stock_minimo" data-variant-minimum></label>
                <span class="variant-state" data-variant-state>Disponible</span><button type="button" class="remove-variant" data-remove-variant aria-label="Eliminar variante"><i class="fa-solid fa-trash"></i></button>
            </div>
        @endforeach
        </div>
        <div id="variantDuplicateError" class="variant-duplicate-error" role="alert" hidden></div>
        @error('variantes')<small class="field-error">{{ $message }}</small>@enderror
    </section>
</div>

<aside class="product-form-side">
    <section class="card product-section"><x-product-section-title icon="fa-tags" title="Precios" help="Valores comerciales del producto." />
        @foreach([['precio_compra','Precio de compra','0',false],['precio_venta','Precio de venta','',true],['precio_oferta','Precio de oferta','',false]] as [$name,$label,$default,$required])
        <label class="field-group"><span>{{ $label }} @if($required)<b>*</b>@endif</span><div class="money-input"><b>{{ $simboloMoneda }}</b><input type="number" name="{{ $name }}" class="form-control" value="{{ old($name,$default) }}" step="0.01" min="{{ $name==='precio_oferta'?'0.01':'0' }}" max="99999999.99" @required($required) placeholder="{{ $name==='precio_oferta'?'Opcional':'' }}"></div>@error($name)<small class="field-error">{{ $message }}</small>@enderror</label>
        @endforeach
        <small class="field-help">La oferta debe ser menor al precio de venta.</small>
    </section>
    <section class="card product-section"><x-product-section-title icon="fa-image" title="Imagen" help="JPG, PNG o WEBP · Máximo 4 MB." />
        <button type="button" id="img-preview" class="product-image-picker" aria-label="Seleccionar imagen"><span id="img-placeholder"><i class="fa-regular fa-images"></i><strong>Subir imagen</strong><small>Haz clic para seleccionar</small></span><img id="img-shown" src="" alt="Vista previa de la imagen seleccionada" hidden></button>
        <input type="file" name="imagen" id="imagenInput" accept="image/jpeg,image/png,image/webp" hidden><button type="button" class="btn btn-outline image-select-button" id="selectImage"><i class="fa-solid fa-arrow-up-from-bracket"></i> Seleccionar imagen</button><small id="imageClientError" class="field-error" hidden></small>@error('imagen')<small class="field-error">{{ $message }}</small>@enderror
    </section>
    <section class="card product-section options-card"><x-product-section-title icon="fa-sliders" title="Publicación" help="Disponibilidad dentro del sistema." />
        <label class="option-switch"><input type="checkbox" name="activo" value="1" @checked(old('activo',true))><span><strong>Producto activo</strong><small>Visible para ventas y catálogo.</small></span></label>
        <label class="option-switch"><input type="checkbox" name="destacado" value="1" @checked(old('destacado',false))><span><strong>Producto destacado</strong><small>Resáltalo en futuras vitrinas.</small></span></label>
    </section>
</aside>
</div>
<div class="product-form-actions"><span><i class="fa-solid fa-circle-info"></i> Los campos con * son obligatorios.</span><div><a href="{{ route('productos.index') }}" class="btn btn-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Guardar producto</button></div></div>
</form>

<template id="variantTemplate"><div class="variant-row" data-variant-row>
    <label><span>Talla</span><input type="text" class="form-control" maxlength="50" placeholder="M" data-field="talla" data-variant-size></label><label><span>Color</span><input type="text" class="form-control" maxlength="80" placeholder="Negro" data-field="color" data-variant-color></label><label><span>Código de barras</span><input type="text" class="form-control" maxlength="100" placeholder="Opcional" data-field="codigo_barra" data-variant-barcode></label><label><span>Stock</span><input type="number" class="form-control" value="0" min="0" max="2000000000" data-field="stock" data-variant-stock></label><label><span>Mínimo</span><input type="number" class="form-control" value="5" min="0" max="2000000000" data-field="stock_minimo" data-variant-minimum></label><span class="variant-state out" data-variant-state>Agotada</span><button type="button" class="remove-variant" data-remove-variant aria-label="Eliminar variante"><i class="fa-solid fa-trash"></i></button>
</div></template>
@endsection
@push('scripts')<script src="{{ asset('js/product-form.js') }}" defer></script><script src="{{ asset('js/product-image.js') }}" defer></script>@endpush
