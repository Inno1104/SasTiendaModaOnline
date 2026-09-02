@extends('layouts.app')
@section('title','Editar Cliente')
@section('page-title','Editar Cliente')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/clientes.css') }}">
@endpush

@section('content')
<div class="page-header customer-page-header">
    <div class="customer-page-heading"><span class="customer-page-icon"><i class="fa-solid fa-user-pen"></i></span><div><span class="customer-eyebrow">{{ $cliente->codigo }}</span><h1>Editar cliente</h1><p>Actualiza los datos de {{ $cliente->nombreCompleto() }}.</p></div></div>
    <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>
<div class="card customer-form-card">
    @include('clientes.partials.form', ['cliente' => $cliente, 'action' => route('clientes.update', $cliente), 'method' => 'PUT', 'submitLabel' => 'Actualizar cliente'])
</div>
@endsection
