<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStoreRequest;
use App\Http\Requests\UpdateStorefrontRequest;
use App\Services\StorePlanUsage;
use App\Services\StoreProfileManager;
use App\Services\StorefrontManager;
use App\Support\StoreLocation;
use App\Support\StorefrontConfiguration;
use Illuminate\Http\Request;

class TiendaController extends Controller
{
    public function __construct(
        private readonly StoreProfileManager $storeProfile,
        private readonly StorePlanUsage $planUsage
    ) {}

    public function edit(Request $request)
    {
        $tienda = $request->user()->tienda;
        abort_unless($tienda, 404);
        $this->authorize('view', $tienda);
        $tienda->load('plan');
        $countries = StoreLocation::COUNTRIES;
        $subscription = $this->planUsage->for($tienda);
        $storefrontDays = StorefrontConfiguration::DAYS;
        $storefrontConditionLabels = StorefrontConfiguration::CONDITION_FIELDS;

        return view('tienda.edit', compact('tienda', 'countries', 'subscription', 'storefrontDays', 'storefrontConditionLabels'));
    }

    public function update(UpdateStoreRequest $request)
    {
        $tienda = $request->user()->tienda;
        abort_unless($tienda, 404);
        $this->authorize('update', $tienda);

        $changed = $this->storeProfile->update($tienda, $request->validated(), $request->user());

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? 'Los datos de tu tienda se actualizaron.' : 'No se detectaron cambios en la tienda.'
        );
    }

    public function updateStorefront(UpdateStorefrontRequest $request, StorefrontManager $manager)
    {
        $store = $request->user()->tienda;
        abort_unless($store, 404);
        $this->authorize('update', $store);

        $changed = $manager->update($store, $request->validated(), $request->user());

        return back()->with(
            $changed ? 'success' : 'info',
            $changed ? 'La configuración pública se actualizó.' : 'No se detectaron cambios en la publicación.'
        );
    }
}
