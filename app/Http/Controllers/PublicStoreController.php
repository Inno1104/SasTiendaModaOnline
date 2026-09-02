<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicCatalogRequest;
use App\Models\Tienda;
use App\Services\GuestCheckoutManager;
use App\Services\PublicCartManager;
use App\Services\PublicCatalogQuery;
use App\Support\StorefrontConfiguration;

final class PublicStoreController extends Controller
{
    public function show(
        PublicCatalogRequest $request,
        PublicCatalogQuery $catalog,
        PublicCartManager $cart,
        GuestCheckoutManager $checkout,
        string $slug,
    ) {
        $store = Tienda::query()->where('slug', $slug)->firstOrFail();
        abort_unless($store->estaPublicada(), 404);
        $catalogData = $catalog->execute($store, $request->validated());

        return view('storefront.show', $catalogData + [
            'store' => $store,
            'contact' => $store->publicContactData(),
            'filters' => $request->validated(),
            'cart' => $cart->snapshot($store, $request->session()),
            'checkout' => $checkout->snapshot($store, $request->session()),
            'orderOperationId' => $checkout->orderOperationId($store, $request->session()),
            'countries' => \App\Support\StoreLocation::COUNTRIES,
            'days' => StorefrontConfiguration::DAYS,
            'conditionLabels' => StorefrontConfiguration::CONDITION_FIELDS,
        ]);
    }
}
