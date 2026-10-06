@extends('layouts.app')
@section('title','Detalle Producto')
@section('page-title','Detalle del Producto')

@section('content')
<div class="page-header">
    <div><h1>{{ $producto->nombre }}</h1></div>
    <div style="display:flex;gap:10px;">
        @can('update', $producto)
            <a href="{{ route('productos.edit',$producto) }}" class="btn btn-outline"><i class="fa-solid fa-pen"></i> Editar</a>
        @endcan
        <a href="{{ route('productos.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
    <div>
        <div class="card" style="margin-bottom:20px;text-align:center;">
            @if($producto->imagen)
                <img src="{{ asset('storage/'.$producto->imagen) }}" alt="Imagen de {{ $producto->nombre }}" style="width:100%;border-radius:10px;object-fit:cover;max-height:260px;">
            @else
                <div style="height:200px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:64px;color:#be185d;">
                    <i class="fa-solid fa-shirt"></i>
                </div>
            @endif
        </div>
        <div class="card">
            <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);font-weight:600;margin-bottom:12px;">Precios</div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:13px;"><span style="color:var(--text-muted);">Compra</span><span>{{ $simboloMoneda }} {{ number_format($producto->precio_compra,2) }}</span></div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:16px;font-weight:700;"><span>Venta</span><span style="color:var(--accent);">{{ $simboloMoneda }} {{ number_format($producto->precio_venta,2) }}</span></div>
            @if($producto->precio_oferta)<div style="display:flex;justify-content:space-between;font-size:13px;color:#16a34a;"><span>Oferta</span><span>{{ $simboloMoneda }} {{ number_format($producto->precio_oferta,2) }}</span></div>@endif
        </div>
    </div>

    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><div class="card-title">Información</div></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                <div><span style="color:var(--text-muted);">Código:</span><br><code style="background:#f3f4f6;padding:3px 8px;border-radius:5px;">{{ $producto->codigo }}</code></div>
                <div><span style="color:var(--text-muted);">Categoría:</span><br><strong>{{ $producto->categoria->nombre ?? '—' }}</strong></div>
                <div><span style="color:var(--text-muted);">Marca:</span><br>{{ $producto->marca->nombre ?? '—' }}</div>
                <div><span style="color:var(--text-muted);">Proveedor:</span><br>{{ $producto->proveedor->nombre ?? '—' }}</div>
                <div><span style="color:var(--text-muted);">Género:</span><br><span class="badge badge-pink">{{ ucfirst($producto->genero) }}</span></div>
                <div><span style="color:var(--text-muted);">Tipo:</span><br>{{ ucfirst($producto->tipo) }}</div>
            </div>
            @if($producto->descripcion)<div style="margin-top:14px;font-size:13px;color:var(--text-muted);">{{ $producto->descripcion }}</div>@endif
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">Stock por Variante</div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Talla</th><th>Detalle de talla</th><th>Color</th><th>Stock</th><th>Stock Mín.</th><th>Estado</th></tr></thead>
                    <tbody>
                        @forelse($producto->variantes as $v)
                        <tr>
                            <td><span class="badge badge-info">{{ $v->talla }}</span></td>
                            <td>{{ $v->detalle_talla ?: '—' }}</td>
                            <td>{{ $v->color }}</td>
                            <td><strong>{{ $v->stock }}</strong></td>
                            <td>{{ $v->stock_minimo }}</td>
                            <td><span class="badge {{ $v->estaAgotada() ? 'badge-danger' : ($v->tieneStockBajo() ? 'badge-warning' : 'badge-success') }}">
                                {{ $v->estaAgotada() ? 'Agotada' : ($v->tieneStockBajo() ? 'Stock bajo' : 'Disponible') }}
                            </span></td>
                        </tr>
                        @empty
                        <tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted);">Sin variantes registradas</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
