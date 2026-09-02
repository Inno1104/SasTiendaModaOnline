<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeProveedorStatusRequest;
use App\Http\Requests\StoreProveedorRequest;
use App\Http\Requests\SupplierIndexRequest;
use App\Http\Requests\UpdateProveedorRequest;
use App\Models\Proveedor;
use App\Services\SupplierManager;
use App\Services\SupplierQuery;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Proveedor::class, 'proveedor');
    }

    public function index(SupplierIndexRequest $request, SupplierQuery $supplierQuery)
    {
        $proveedores = $supplierQuery
            ->apply(Proveedor::query(), $request->validated())
            ->paginate(15)
            ->withQueryString();

        return view('proveedores.index', compact('proveedores'));
    }

    public function create()
    {
        return view('proveedores.create');
    }

    public function store(StoreProveedorRequest $request, SupplierManager $manager)
    {
        $manager->create($request->validated(), $request->user());

        return redirect()->route('proveedores.index')->with('success', 'Proveedor registrado.');
    }

    public function edit(Proveedor $proveedor)
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    public function update(UpdateProveedorRequest $request, Proveedor $proveedor, SupplierManager $manager)
    {
        $manager->update($proveedor, $request->validated(), $request->user());

        return redirect()->route('proveedores.index')->with('success', 'Proveedor actualizado.');
    }

    public function destroy(Proveedor $proveedor, Request $request, SupplierManager $manager)
    {
        $manager->changeStatus($proveedor, false, $request->user());

        return back()->with('success', 'Proveedor desactivado.');
    }

    public function changeStatus(
        ChangeProveedorStatusRequest $request,
        Proveedor $proveedor,
        SupplierManager $manager
    ) {
        $active = $request->boolean('activo');
        $changed = $manager->changeStatus($proveedor, $active, $request->user());

        return back()->with(
            'success',
            $changed
                ? ($active ? 'Proveedor activado.' : 'Proveedor desactivado.')
                : 'El proveedor ya se encontraba en ese estado.'
        );
    }
}
