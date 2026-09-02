@extends('layouts.app')
@section('title','Editar Producto')
@section('page-title','Editar Producto')

@section('content')
<div class="page-header">
    <div><h1>Editar: {{ $producto->nombre }}</h1></div>
    <a href="{{ route('productos.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<form method="POST" action="{{ route('productos.update',$producto) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
        <div>
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
        </div>

        <div>
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
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><div class="card-title">Imagen del Producto</div></div>
                <div style="text-align:center;">
                    <button type="button" id="img-preview" aria-label="Seleccionar una nueva imagen" style="width:100%;height:190px;padding:0;border:2px dashed var(--border);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;overflow:hidden;cursor:pointer;background:#fafbff;">
                        <div id="img-placeholder" style="{{ $producto->imagen ? 'display:none;' : '' }}">
                            <i class="fa-solid fa-image" style="font-size:34px;color:#c7cada;"></i><br>
                            <small style="color:var(--text-muted);">Seleccionar una imagen</small>
                        </div>
                        <img id="img-shown" src="{{ $producto->imagen ? asset('storage/'.$producto->imagen) : '' }}" alt="Imagen de {{ $producto->nombre }}" style="{{ $producto->imagen ? '' : 'display:none;' }}width:100%;height:100%;object-fit:cover;">
                    </button>
                    <input type="file" name="imagen" id="imagenInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
                    <button type="button" class="btn btn-outline btn-sm" id="selectImage"><i class="fa-solid fa-upload"></i> Cambiar imagen</button>
                    <div style="margin-top:8px;color:var(--text-muted);font-size:10px;">JPG, PNG o WEBP · Máximo 4 MB</div>
                    <small id="imageClientError" hidden style="display:block;margin-top:6px;color:#dc2626;"></small>
                    @error('imagen')<small style="display:block;margin-top:6px;color:#dc2626;">{{ $message }}</small>@enderror
                </div>
            </div>
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
@endsection

@push('scripts')
<script src="{{ asset('js/product-image.js') }}"></script>
@endpush
