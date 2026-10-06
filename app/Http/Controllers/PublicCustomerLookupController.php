<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Tienda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicCustomerLookupController extends Controller
{
    public function __invoke(Request $request, string $slug): JsonResponse
    {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);

        $request->merge([
            'dni' => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $request->input('dni')))),
        ]);
        $validated = $request->validate([
            'dni' => ['required', 'string', 'min:5', 'max:30', 'regex:/^[A-Z0-9.-]+$/i'],
        ]);
        $dni = $validated['dni'];

        $customer = Cliente::withoutGlobalScopes()
            ->where('tienda_id', $store->id)
            ->where('dni', $dni)
            ->where('activo', true)
            ->first(['nombre', 'apellido', 'dni', 'email', 'telefono', 'direccion', 'ciudad']);

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'customer' => $customer->only(['nombre', 'apellido', 'dni', 'email', 'telefono', 'direccion', 'ciudad']),
        ]);
    }
}
