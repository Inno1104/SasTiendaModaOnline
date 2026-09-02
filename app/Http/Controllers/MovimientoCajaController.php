<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMovimientoCajaRequest;
use App\Models\Caja;
use App\Services\CashMovementManager;
use Illuminate\Validation\ValidationException;

class MovimientoCajaController extends Controller
{
    public function store(StoreMovimientoCajaRequest $request, Caja $caja, CashMovementManager $manager)
    {
        try {
            $manager->create($caja, $request->user(), $request->validated());

            return back()->with('success', 'Movimiento de caja registrado correctamente.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }
}
