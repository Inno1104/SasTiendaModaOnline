<div class="cash-operations-grid">
    @can('manageMovements', $caja)
        <section class="card cash-movement-form" aria-labelledby="movement-form-title">
            <div class="cash-panel-heading"><h2 id="movement-form-title"><i class="fa-solid fa-money-bill-transfer"></i> Registrar movimiento</h2></div>
            <form method="POST" action="{{ route('caja.movimientos.store', $caja) }}" class="cash-form-grid" data-cash-form>
                @csrf
                <div><label class="form-label" for="tipo">Tipo</label><select class="form-control" id="tipo" name="tipo" required><option value="ingreso" @selected(old('tipo') === 'ingreso')>Ingreso de efectivo</option><option value="egreso" @selected(old('tipo') === 'egreso')>Egreso de efectivo</option></select></div>
                <div><label class="form-label" for="concepto">Concepto</label><input class="form-control" id="concepto" name="concepto" maxlength="150" value="{{ old('concepto') }}" placeholder="Ej.: Pago de transporte" required></div>
                <div><label class="form-label" for="monto">Monto</label><div class="cash-money-input"><span>{{ $simboloMoneda }}</span><input class="form-control" id="monto" type="number" name="monto" min="0.01" max="99999999" step="0.01" value="{{ old('monto') }}" placeholder="0.00" required></div></div>
                <div><label class="form-label" for="movimiento_observaciones">Observación (opcional)</label><textarea class="form-control" id="movimiento_observaciones" name="observaciones" maxlength="500" rows="2" placeholder="Escribe una observación...">{{ old('observaciones') }}</textarea></div>
                <button class="btn btn-primary cash-submit-wide"><i class="fa-solid fa-floppy-disk"></i> Guardar movimiento</button>
            </form>
        </section>
    @endcan
    <section class="card cash-movement-list" aria-labelledby="movement-list-title">
        <div class="cash-panel-heading"><h2 id="movement-list-title"><i class="fa-solid fa-list"></i> Movimientos del turno</h2><a href="{{ route('caja.index') }}" class="btn btn-outline cash-refresh"><i class="fa-solid fa-rotate"></i> Actualizar</a></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Hora</th><th>Tipo</th><th>Concepto</th><th>Responsable</th><th>Monto</th></tr></thead>
                <tbody>
                @forelse($caja->movimientos as $movimiento)
                    <tr>
                        <td>{{ $movimiento->created_at->format('H:i') }}</td>
                        <td><span class="cash-movement-type {{ $movimiento->tipo }}"><i class="fa-solid {{ $movimiento->tipo === 'ingreso' ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>{{ ucfirst($movimiento->tipo) }}</span></td>
                        <td>{{ $movimiento->concepto }}</td>
                        <td>{{ $movimiento->usuario?->name ?? '—' }}</td>
                        <td class="{{ $movimiento->tipo === 'ingreso' ? 'cash-total-positive' : 'cash-total-negative' }}">{{ $movimiento->tipo === 'ingreso' ? '+' : '−' }} {{ $simboloMoneda }} {{ number_format($movimiento->monto, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="cash-empty-state cash-movement-empty"><span class="cash-empty-icon"><i class="fa-solid fa-wallet"></i></span><strong>Sin movimientos adicionales</strong><span>Los ingresos y egresos del turno aparecerán aquí.</span></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
