<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\User;
use App\Http\Requests\CashRegisterHistoryRequest;
use App\Http\Requests\CloseCajaRequest;
use App\Http\Requests\OpenCajaRequest;
use App\Services\CashRegisterManager;
use App\Services\CashRegisterSummary;
use App\Services\CashRegisterHistoryQuery;
use Illuminate\Validation\ValidationException;

class CajaController extends Controller
{
    public function index(
        CashRegisterHistoryRequest $request,
        CashRegisterSummary $summary,
        CashRegisterHistoryQuery $historyQuery
    )
    {
        $filters = $request->validated();
        $abierta = Caja::with([
            'usuario',
            'usuarioCierre',
            'movimientos' => fn ($query) => $query->with('usuario')->latest()->limit(20),
        ])->where('estado', 'abierta')->latest('id')->first();
        $historial = $historyQuery->apply(Caja::query(), $filters)
            ->with(['usuario', 'usuarioCierre'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();
        $responsables = User::query()
            ->where('tienda_id', $request->user()->tienda_id)
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $resumenAbierta = $abierta ? $summary->calculate($abierta) : null;

        return view('caja.index', compact('abierta', 'historial', 'resumenAbierta', 'responsables', 'filters'));
    }

    public function show(Caja $caja, CashRegisterSummary $summary)
    {
        $this->authorize('view', $caja);
        $caja->load(['usuario', 'usuarioCierre']);
        $resumen = $summary->calculate($caja);
        $ventas = $caja->ventas()->with(['cliente', 'vendedor', 'pagos'])
            ->latest('id')->paginate(15, ['*'], 'ventas_page')->withQueryString();
        $movimientos = $caja->movimientos()->with('usuario')
            ->latest('id')->paginate(20, ['*'], 'movimientos_page')->withQueryString();

        return view('caja.show', compact('caja', 'resumen', 'ventas', 'movimientos'));
    }

    public function export(CashRegisterHistoryRequest $request, CashRegisterHistoryQuery $historyQuery)
    {
        $registers = $historyQuery->apply(Caja::query(), $request->validated())
            ->with(['usuario', 'usuarioCierre'])
            ->latest('id');

        return response()->streamDownload(function () use ($registers) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Caja', 'Fecha', 'Responsable', 'Apertura', 'Cierre', 'Estado', 'Esperado', 'Contado', 'Diferencia', 'Conciliación', 'Ventas']);
            foreach ($registers->lazyByIdDesc(200) as $register) {
                fputcsv($output, [
                    $register->id,
                    $register->fecha?->format('Y-m-d'),
                    $register->usuario?->name,
                    $register->apertura?->format('Y-m-d H:i:s'),
                    $register->cierre?->format('Y-m-d H:i:s'),
                    $register->estado,
                    $register->efectivo_esperado,
                    $register->efectivo_contado,
                    $register->diferencia,
                    $register->estado_conciliacion,
                    $register->total_ventas,
                ]);
            }
            fclose($output);
        }, 'historial-cajas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function open(OpenCajaRequest $request, CashRegisterManager $manager)
    {
        $data = $request->validated();

        try {
            $manager->open($request->user(), (float) $data['monto_inicial'], $data['observaciones'] ?? null);
            return back()->with('success', 'Caja abierta correctamente.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    public function close(CloseCajaRequest $request, Caja $caja, CashRegisterManager $manager)
    {
        try {
            $manager->close($caja, $request->user(), $request->validated());
            return back()->with('success', 'Caja cerrada correctamente.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }
}
