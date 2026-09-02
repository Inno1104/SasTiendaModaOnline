@extends('layouts.app')
@section('title','Nuevo Cliente')
@section('page-title','Nuevo Cliente')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}">
@endpush

@section('content')
<div class="page-header customer-page-header">
    <div class="customer-page-heading"><span class="customer-page-icon"><i class="fa-solid fa-user-plus"></i></span><div><span class="customer-eyebrow">Gestión de clientes</span><h1>Nuevo cliente</h1><p>Registra su información para asociarla a futuras ventas.</p></div></div>
    <a href="{{ route('clientes.index') }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>
<div class="card customer-form-card">
    @include('clientes.partials.form', ['cliente' => null, 'action' => route('clientes.store'), 'method' => 'POST', 'submitLabel' => 'Guardar cliente'])
</div>
@endsection
