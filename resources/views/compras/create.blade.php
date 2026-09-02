@extends('layouts.app')
@section('title','Nueva compra')
@section('page-title','Nueva compra')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/compras.css') }}">
@endpush

@section('content')
<div class="purchase-header">
    <div><div class="purchase-header__eyebrow">Recepción de inventario</div><h1>Registrar compra</h1><div class="breadcrumb"><a href="{{ route('compras.index') }}">Compras</a><span class="breadcrumb-sep">›</span>Nueva</div></div>
    <a href="{{ route('compras.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<x-purchases.form :proveedores="$proveedores" :categorias="$categorias" :selected-variants="$selectedVariants" />
@endsection

@push('scripts')
<script src="{{ asset('js/compras.js') }}" defer></script>
@endpush
