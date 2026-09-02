@extends('layouts.app')
@section('title', 'Editar proveedor')
@section('page-title', 'Editar proveedor')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/proveedores.css') }}">
@endpush

@section('content')
<div class="supplier-page-header">
    <div class="supplier-heading">
        <span class="supplier-heading-icon"><i class="fa-solid fa-truck-fast"></i></span>
        <div><span class="supplier-eyebrow">Red de abastecimiento</span><h1>Editar proveedor</h1><p>Actualiza los datos de {{ $proveedor->nombre }} sin alterar su historial.</p></div>
    </div>
    <a href="{{ route('proveedores.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

@include('proveedores._form', ['action' => route('proveedores.update', $proveedor), 'method' => 'PUT', 'submitLabel' => 'Guardar cambios'])
@endsection
