@extends('layouts.app')
@section('title','Configuración')
@section('page-title','Configuración')
@push('styles')<link rel="stylesheet" href="{{ asset('css/store-settings.css') }}">@endpush

@section('content')
<div class="page-header">
    <div><h1>Configuración fiscal y monetaria</h1><div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span class="breadcrumb-sep">›</span>Configuración</div></div>
</div>

<section class="card store-settings" aria-labelledby="settings-title">
    <div class="card-header"><div class="card-title" id="settings-title"><i class="fa-solid fa-receipt"></i> Preferencias de venta y comprobantes</div></div>
    <p class="store-settings-intro">Estos datos afectan los cálculos y la numeración de los próximos comprobantes. Los comprobantes históricos conservarán su información original.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert" aria-live="assertive"><strong>No se guardaron los cambios.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('configuracion.update') }}" data-fiscal-settings-form>
        @csrf
        <input type="hidden" name="configuracion_version" value="{{ $tienda->configuracion_version }}">
        <input type="hidden" name="confirmar_cambio" value="0" data-fiscal-confirmation>

        <fieldset class="settings-fieldset">
            <legend>Moneda e impuesto</legend>
            <div class="form-row">
                <div class="form-group"><label class="form-label" for="settings-currency">Moneda</label><select id="settings-currency" name="moneda" class="form-control @error('moneda') is-invalid @enderror" required data-currency-select>@foreach($currencies as $code => $currency)<option value="{{ $code }}" data-symbol="{{ $currency['symbol'] }}" @selected(old('moneda',$tienda->moneda ?? 'USD') === $code)>{{ $code }} — {{ $currency['name'] }}</option>@endforeach</select>@error('moneda')<small class="field-error">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label class="form-label" for="settings-symbol">Símbolo calculado</label><output id="settings-symbol" class="form-control settings-symbol" data-currency-symbol>{{ $currencies[old('moneda',$tienda->moneda ?? 'USD')]['symbol'] ?? '$' }}</output><small>El servidor asignará este símbolo automáticamente.</small></div>
                <div class="form-group"><label class="form-label" for="settings-tax">Impuesto (%)</label><input id="settings-tax" type="number" min="0" max="100" step="0.01" name="igv" class="form-control @error('igv') is-invalid @enderror" value="{{ old('igv',$tienda->igv ?? 0) }}" required>@error('igv')<small class="field-error">{{ $message }}</small>@enderror</div>
            </div>
            <label class="settings-check"><input type="checkbox" name="precios_incluyen_igv" value="1" @checked(old('precios_incluyen_igv',$tienda->precios_incluyen_igv))><span><strong>Los precios incluyen el impuesto</strong><small>El precio mostrado al cliente ya representa el valor final.</small></span></label>
        </fieldset>

        <fieldset class="settings-fieldset">
            <legend>Series de comprobantes</legend>
            <div class="form-row">
                @foreach(['serie_boleta'=>'Boleta','serie_factura'=>'Factura','serie_ticket'=>'Ticket'] as $field => $label)
                    <div class="form-group"><label class="form-label" for="settings-{{ $field }}">Serie de {{ mb_strtolower($label) }}</label><input id="settings-{{ $field }}" type="text" name="{{ $field }}" class="form-control settings-series @error($field) is-invalid @enderror" value="{{ old($field,$tienda->{$field}) }}" maxlength="10" autocomplete="off" required>@error($field)<small class="field-error">{{ $message }}</small>@enderror</div>
                @endforeach
            </div>
        </fieldset>

        <fieldset class="settings-fieldset">
            <legend>Pago por transferencia bancaria</legend>
            <label class="settings-check"><input type="hidden" name="transferencia_activa" value="0"><input type="checkbox" name="transferencia_activa" value="1" @checked(old('transferencia_activa',$tienda->transferencia_activa))><span><strong>Aceptar transferencias en la tienda online</strong><small>El cliente verá estos datos después de confirmar su pedido.</small></span></label>
            <div class="form-row">
                <div class="form-group"><label class="form-label">Banco</label><input class="form-control" name="transferencia_banco" value="{{ old('transferencia_banco',$tienda->transferencia_banco) }}" maxlength="100"></div>
                <div class="form-group"><label class="form-label">Tipo de cuenta</label><select class="form-control" name="transferencia_tipo_cuenta"><option value="">Seleccionar</option><option value="ahorros" @selected(old('transferencia_tipo_cuenta',$tienda->transferencia_tipo_cuenta)==='ahorros')>Ahorros</option><option value="corriente" @selected(old('transferencia_tipo_cuenta',$tienda->transferencia_tipo_cuenta)==='corriente')>Corriente</option></select></div>
                <div class="form-group"><label class="form-label">Número de cuenta</label><input class="form-control" name="transferencia_numero_cuenta" value="{{ old('transferencia_numero_cuenta',$tienda->transferencia_numero_cuenta) }}" maxlength="60"></div>
                <div class="form-group"><label class="form-label">Titular</label><input class="form-control" name="transferencia_titular" value="{{ old('transferencia_titular',$tienda->transferencia_titular) }}" maxlength="150"></div>
                <div class="form-group"><label class="form-label">Identificación del titular</label><input class="form-control" name="transferencia_identificacion" value="{{ old('transferencia_identificacion',$tienda->transferencia_identificacion) }}" maxlength="30"></div>
                <div class="form-group"><label class="form-label">Instrucciones adicionales</label><input class="form-control" name="transferencia_instrucciones" value="{{ old('transferencia_instrucciones',$tienda->transferencia_instrucciones) }}" maxlength="500"></div>
            </div>
        </fieldset>

        <div class="settings-reason">
            <label class="form-label" for="settings-reason">Motivo del cambio fiscal</label>
            <textarea id="settings-reason" name="motivo_cambio" class="form-control @error('motivo_cambio') is-invalid @enderror" maxlength="500" rows="3" placeholder="Obligatorio al cambiar moneda, impuesto o series">{{ old('motivo_cambio') }}</textarea>
            <small>Describe por qué se modifica la configuración. Se guardará en la auditoría.</small>
            @error('motivo_cambio')<small class="field-error">{{ $message }}</small>@enderror
        </div>
        @error('configuracion_version')<div class="alert alert-warning" role="alert">{{ $message }}</div>@enderror
        <button type="submit" class="btn btn-primary" data-submit><i class="fa-solid fa-floppy-disk"></i> Guardar configuración</button>
    </form>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/store-settings.js') }}"></script>@endpush
