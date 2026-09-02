@extends('layouts.app')
@section('title', 'Nuevo proveedor')
@section('page-title', 'Nuevo proveedor')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/proveedores.css') }}">
@endpush

@section('content')
<div class="supplier-page-header">
    <div class="supplier-heading">
        <span class="supplier-heading-icon"><i class="fa-solid fa-truck-fast"></i></span>
        <div><span class="supplier-eyebrow">Red de abastecimiento</span><h1>Nuevo proveedor</h1><p>Registra su identidad fiscal y los datos de contacto comercial.</p></div>
    </div>
    <a href="{{ route('proveedores.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

@include('proveedores._form', ['action' => route('proveedores.store'), 'method' => 'POST', 'submitLabel' => 'Guardar proveedor'])
@endsection
