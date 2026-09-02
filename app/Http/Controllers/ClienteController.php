<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeClienteStatusRequest;
use App\Http\Requests\ClienteIndexRequest;
use App\Http\Requests\StoreClienteRequest;
use App\Http\Requests\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteCommercialSummary;
use App\Services\ClienteCreator;
use App\Services\ClienteQuery;
use App\Services\ClienteStatusManager;
use Illuminate\Validation\ValidationException;

class ClienteController extends Controller
{
    public function __construct(private readonly ClienteCreator $clienteCreator)
    {
        $this->authorizeResource(Cliente::class, 'cliente');
    }

    public function index(ClienteIndexRequest $request, ClienteQuery $clienteQuery)
    {
        $filters = $request->validated();
        $clientes = $clienteQuery->apply(Cliente::query(), $filters)
            ->with('estadoCambiadoPor')
            ->paginate(15)
            ->withQueryString();
        $ciudades = Cliente::query()->whereNotNull('ciudad')->where('ciudad', '<>', '')
            ->distinct()->orderBy('ciudad')->pluck('ciudad');

        return view('clientes.index', compact('clientes', 'ciudades', 'filters'));
    }

    public function export(ClienteIndexRequest $request, ClienteQuery $clienteQuery)
    {
        $customers = $clienteQuery->apply(Cliente::query(), $request->validated());

        return response()->streamDownload(function () use ($customers) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Código', 'Nombre', 'Apellido', 'Documento', 'Correo', 'Teléfono', 'Ciudad', 'Estado', 'Compras', 'Total gastado', 'Última compra', 'Registrado']);

            foreach ($customers->cursor() as $customer) {
                fputcsv($output, [
                    $this->safeCsvValue($customer->codigo),
                    $this->safeCsvValue($customer->nombre),
                    $this->safeCsvValue($customer->apellido),
                    $this->safeCsvValue($customer->dni),
                    $this->safeCsvValue($customer->email),
                    $this->safeCsvValue($customer->telefono),
                    $this->safeCsvValue($customer->ciudad),
                    $customer->activo ? 'Activo' : 'Inactivo',
                    $customer->compras_completadas,
                    number_format((float) ($customer->total_gastado ?? 0), 2, '.', ''),
                    $customer->ultima_compra,
                    $customer->created_at?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($output);
        }, 'clientes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create()
    {
        return view('clientes.create');
    }

    public function store(StoreClienteRequest $request)
    {
        $this->clienteCreator->create($request->validated(), (int) $request->user()->tienda_id);

        return redirect()->route('clientes.index')->with('success', 'Cliente registrado exitosamente.');
    }

    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente)
    {
        $cliente->update($request->validated());

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado.');
    }

    public function show(Cliente $cliente, ClienteCommercialSummary $summary)
    {
        $cliente->load('estadoCambiadoPor');
        $resumenComercial = $summary->calculate($cliente);
        $ventas = $cliente->ventas()
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('clientes.show', compact('cliente', 'resumenComercial', 'ventas'));
    }

    public function changeStatus(ChangeClienteStatusRequest $request, Cliente $cliente, ClienteStatusManager $manager)
    {
        $data = $request->validated();

        try {
            $manager->change($cliente, $request->user(), (bool) $data['activo'], $data['motivo']);

            return back()->with('success', (bool) $data['activo'] ? 'Cliente reactivado correctamente.' : 'Cliente desactivado correctamente.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }

    private function safeCsvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $value) ? "'".$value : $value;
    }
}
