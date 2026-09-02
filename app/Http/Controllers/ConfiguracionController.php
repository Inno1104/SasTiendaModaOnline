<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStoreSettingsRequest;
use App\Services\StoreSettingsManager;
use App\Support\StoreCurrency;
use Illuminate\Http\Request;

class ConfiguracionController extends Controller
{
    public function __construct(private readonly StoreSettingsManager $settings) {}

    public function index(Request $request)
    {
        $tienda = $request->user()->tienda;
        abort_unless($tienda, 404);
        $this->authorize('view', $tienda);
        $currencies = StoreCurrency::ALLOWED;

        return view('configuracion.index', compact('tienda', 'currencies'));
    }

    public function update(UpdateStoreSettingsRequest $request)
    {
        $tienda = $request->user()->tienda;
        abort_unless($tienda, 404);
        $this->authorize('update', $tienda);
        $changed = $this->settings->update($tienda, $request->validated(), $request->user());

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? 'Configuración guardada correctamente.' : 'No se detectaron cambios en la configuración.'
        );
    }
}
