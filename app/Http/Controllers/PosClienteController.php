<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePosClienteRequest;
use App\Models\Cliente;
use App\Services\ClienteCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosClienteController extends Controller
{
    public function __construct(private readonly ClienteCreator $clienteCreator) {}

    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = Cliente::query()->where('activo', true)
            ->where(function ($builder) use ($query) {
                $like = '%'.$query.'%';
                $builder->where('nombre', 'like', $like)->orWhere('apellido', 'like', $like)
                    ->orWhere('codigo', 'like', $like)->orWhere('dni', 'like', $like)
                    ->orWhere('telefono', 'like', $like);
            })
            ->orderBy('nombre')->limit(15)->get(['id', 'codigo', 'nombre', 'apellido', 'dni', 'telefono']);

        return response()->json(['data' => $clients]);
    }

    public function store(StorePosClienteRequest $request): JsonResponse
    {
        $client = $this->clienteCreator->create($request->validated(), (int) $request->user()->tienda_id);

        return response()->json(['data' => $client->only(['id', 'codigo', 'nombre', 'apellido', 'dni', 'telefono'])], 201);
    }
}
