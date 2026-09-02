@extends('layouts.app')
@section('title', 'Caja')
@section('page-title', 'Caja')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/caja.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/caja.js') }}" defer></script>
@endpush

@section('content')
<div class="page-header cash-page-header">
    <div class="cash-page-heading">
        <span class="cash-page-icon"><i class="fa-solid fa-vault"></i></span>
        <div><span class="cash-eyebrow">Operación diaria</span><h1>Control de caja</h1><div class="breadcrumb">Apertura, movimientos, conciliación e historial</div></div>
    </div>
    @if($abierta)<span class="cash-live-status"><span></span> Turno activo</span>@endif
</div>

<div class="card cash-register-card {{ $abierta ? 'is-open' : 'is-closed' }}">
    @if($abierta)
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-lock-open cash-icon-success"></i> Turno abierto</div><span class="cash-register-number">Caja #{{ $abierta->id }}</span></div>
        <div class="cash-summary">
            <div class="cash-summary-item"><span class="cash-summary-icon accent"><i class="fa-regular fa-user"></i></span><div><span>Responsable</span><strong>{{ $abierta->usuario?->name ?? 'Usuario no disponible' }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon accent"><i class="fa-regular fa-calendar"></i></span><div><span>Apertura</span><strong>{{ $abierta->apertura?->format('d/m/Y H:i') }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon accent"><i class="fa-regular fa-clock"></i></span><div><span>Duración</span><strong>{{ $abierta->apertura?->diffForHumans(now(), true) ?? '—' }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon accent"><i class="fa-solid fa-money-bill"></i></span><div><span>Efectivo inicial</span><strong>{{ $simboloMoneda }} {{ number_format($abierta->monto_inicial, 2) }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon success"><i class="fa-solid fa-circle-plus"></i></span><div><span>Otros ingresos</span><strong class="cash-total-positive">+ {{ $simboloMoneda }} {{ number_format($abierta->total_ingresos, 2) }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon danger"><i class="fa-solid fa-circle-minus"></i></span><div><span>Egresos</span><strong class="cash-total-negative">− {{ $simboloMoneda }} {{ number_format($abierta->total_egresos, 2) }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon neutral"><i class="fa-solid fa-wallet"></i></span><div><span>Efectivo esperado</span><strong>{{ $simboloMoneda }} {{ number_format($resumenAbierta['efectivo_esperado'], 2) }}</strong></div></div>
            <div class="cash-summary-item"><span class="cash-summary-icon neutral"><i class="fa-solid fa-chart-column"></i></span><div><span>Ventas completadas</span><strong>{{ $resumenAbierta['ventas_cantidad'] }} · {{ $simboloMoneda }} {{ number_format($resumenAbierta['total_ventas'], 2) }}</strong></div></div>
        </div>
        <div class="cash-payment-breakdown" aria-label="Desglose por forma de pago">
            @foreach($resumenAbierta['pagos'] as $metodo => $total)
                @php($iconoPago = ['efectivo' => 'fa-money-bill', 'tarjeta' => 'fa-credit-card', 'transferencia' => 'fa-right-left', 'yape' => 'fa-mobile-screen', 'plin' => 'fa-mobile-button'][$metodo] ?? 'fa-wallet')
                <span class="cash-payment-chip"><i class="fa-solid {{ $iconoPago }}"></i>{{ ucfirst($metodo) }}: <strong>{{ $simboloMoneda }} {{ number_format($total, 2) }}</strong></span>
            @endforeach
            <span class="cash-payment-chip"><i class="fa-solid fa-rotate-left"></i>Vuelto: <strong>{{ $simboloMoneda }} {{ number_format($resumenAbierta['vuelto'], 2) }}</strong></span>
        </div>
        @if($abierta->observaciones)
            <p class="cash-note"><strong>Observación:</strong> {{ $abierta->observaciones }}</p>
        @endif
        @can('close', $abierta)
            <button type="button" class="btn btn-primary" id="openCashClose"><i class="fa-solid fa-scale-balanced"></i> Contar y cerrar turno</button>
        @else
            <p class="cash-help">Solo el responsable del turno o un administrador puede cerrarlo.</p>
        @endcan

    @else
        <div class="card-header"><div class="card-title"><i class="fa-solid fa-cash-register cash-icon-accent"></i> Preparar nuevo turno</div></div>
        @can('open', App\Models\Caja::class)
            <form method="POST" action="{{ route('caja.open') }}" data-cash-form>
                @csrf
                <div class="cash-open-field amount">
                    <label class="form-label" for="monto_inicial">Efectivo inicial</label>
                    <input class="form-control" id="monto_inicial" type="number" name="monto_inicial" min="0" max="99999999" step="0.01" value="{{ old('monto_inicial', 0) }}" required>
                </div>
                <div class="cash-open-field">
                    <label class="form-label" for="observaciones">Observación de apertura (opcional)</label>
                    <textarea class="form-control" id="observaciones" name="observaciones" maxlength="500" rows="3" placeholder="Ej.: Turno de mañana, fondo recibido sin novedades">{{ old('observaciones') }}</textarea>
                </div>
                <button class="btn btn-primary"><i class="fa-solid fa-lock-open"></i> Abrir turno de caja</button>
            </form>
        @else
            <p class="cash-help">Solicita a un administrador o cajero autorizado que abra el turno.</p>
        @endcan
    @endif
</div>

@if($abierta)
    @include('caja.partials.movements', ['caja' => $abierta])
@endif

<div class="card cash-history-card">
    <div class="cash-history-toolbar">
        <div><div class="card-title"><i class="fa-solid fa-clock-rotate-left cash-icon-accent"></i> Historial de turnos</div><p>Consulta, concilia y exporta las operaciones anteriores.</p></div>
        <a class="btn btn-secondary" href="{{ route('caja.export', request()->query()) }}"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
    </div>
    <form method="GET" action="{{ route('caja.index') }}" class="cash-history-filters">
        <div><label class="form-label" for="q">Caja o responsable</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="#15 o nombre"></div>
        <div><label class="form-label" for="desde">Desde</label><input class="form-control" id="desde" type="date" name="desde" value="{{ $filters['desde'] ?? '' }}"></div>
        <div><label class="form-label" for="hasta">Hasta</label><input class="form-control" id="hasta" type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}"></div>
        <div><label class="form-label" for="estado">Estado</label><select class="form-control" id="estado" name="estado"><option value="">Todos</option><option value="abierta" @selected(($filters['estado'] ?? '') === 'abierta')>Abierta</option><option value="cerrada" @selected(($filters['estado'] ?? '') === 'cerrada')>Cerrada</option></select></div>
        <div><label class="form-label" for="conciliacion">Conciliación</label><select class="form-control" id="conciliacion" name="conciliacion"><option value="">Todas</option>@foreach(['exacto' => 'Exacto', 'sobrante' => 'Sobrante', 'faltante' => 'Faltante'] as $value => $label)<option value="{{ $value }}" @selected(($filters['conciliacion'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="form-label" for="responsable_id">Responsable</label><select class="form-control" id="responsable_id" name="responsable_id"><option value="">Todos</option>@foreach($responsables as $responsable)<option value="{{ $responsable->id }}" @selected((string)($filters['responsable_id'] ?? '') === (string)$responsable->id)>{{ $responsable->name }}</option>@endforeach</select></div>
        <div class="cash-filter-actions"><button class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filtrar</button><a class="btn btn-secondary" href="{{ route('caja.index') }}">Limpiar</a></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Caja</th><th>Fecha</th><th>Responsable</th><th>Apertura</th><th>Cierre</th><th>Estado</th><th>Esperado</th><th>Contado</th><th>Diferencia</th><th>Conciliación</th><th>Ventas</th><th></th></tr></thead>
            <tbody>
            @forelse($historial as $caja)
                <tr>
                    <td>#{{ $caja->id }}</td>
                    <td>{{ $caja->fecha->format('d/m/Y') }}</td>
                    <td>{{ $caja->usuario?->name ?? '—' }}</td>
                    <td>{{ $caja->apertura?->format('H:i') ?? '—' }}</td>
                    <td>{{ $caja->cierre?->format('H:i') ?? '—' }}</td>
                    <td>{{ ucfirst($caja->estado) }}</td>
                    <td>{{ $caja->efectivo_esperado === null ? '—' : $simboloMoneda.' '.number_format($caja->efectivo_esperado, 2) }}</td>
                    <td>{{ $caja->efectivo_contado === null ? '—' : $simboloMoneda.' '.number_format($caja->efectivo_contado, 2) }}</td>
                    <td class="cash-difference {{ $caja->estado_conciliacion }}">{{ $caja->diferencia === null ? '—' : ($caja->diferencia > 0 ? '+' : '').$simboloMoneda.' '.number_format($caja->diferencia, 2) }}</td>
                    <td>@if($caja->estado_conciliacion)<span class="reconciliation-status {{ $caja->estado_conciliacion }}">{{ ucfirst($caja->estado_conciliacion) }}</span>@else — @endif</td>
                    <td>{{ $simboloMoneda }} {{ number_format($caja->total_ventas, 2) }}</td>
                    <td><a class="btn btn-secondary cash-view-link" href="{{ route('caja.show', $caja) }}" aria-label="Ver detalle de caja {{ $caja->id }}"><i class="fa-solid fa-eye"></i> Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="12"><div class="cash-empty-state"><i class="fa-solid fa-magnifying-glass"></i><strong>Sin resultados</strong><span>No existen turnos que coincidan con los filtros seleccionados.</span></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $historial->links() }}
</div>

@if($abierta && auth()->user()->can('close', $abierta))
<dialog id="cashCloseDialog" class="cash-close-dialog" data-expected="{{ $resumenAbierta['efectivo_esperado'] }}" data-symbol="{{ $simboloMoneda }}" data-open-on-load="{{ $errors->has('efectivo_contado') || $errors->has('observaciones_cierre') ? '1' : '0' }}">
    <form method="POST" action="{{ route('caja.close', $abierta) }}" class="cash-close-content" data-cash-form>
        @csrf
        <h2>Conciliar y cerrar turno</h2>
        <p>Cuenta físicamente el efectivo antes de confirmar el cierre.</p>
        <div class="cash-reconciliation-preview">
            <div><span>Efectivo esperado</span><strong>{{ $simboloMoneda }} {{ number_format($resumenAbierta['efectivo_esperado'], 2) }}</strong></div>
            <div><span>Diferencia</span><strong id="cashDifference" class="cash-difference exacto">{{ $simboloMoneda }} 0.00</strong></div>
        </div>
        <div class="cash-form-grid">
            <div><label class="form-label" for="efectivo_contado">Efectivo contado</label><input class="form-control" id="efectivo_contado" name="efectivo_contado" type="number" min="0" max="99999999" step="0.01" value="{{ old('efectivo_contado', $resumenAbierta['efectivo_esperado']) }}" required></div>
            <div><label class="form-label" for="observaciones_cierre">Justificación del cierre</label><textarea class="form-control" id="observaciones_cierre" name="observaciones_cierre" maxlength="500" rows="3" placeholder="Obligatoria cuando exista faltante o sobrante">{{ old('observaciones_cierre') }}</textarea></div>
            <div>Resultado: <span id="cashReconciliationStatus" class="reconciliation-status exacto">Exacto</span></div>
        </div>
        <div class="cash-dialog-actions"><button type="button" class="btn btn-secondary" id="cancelCashClose">Cancelar</button><button class="btn btn-primary"><i class="fa-solid fa-lock"></i> Confirmar cierre</button></div>
    </form>
</dialog>
@endif
@endsection
