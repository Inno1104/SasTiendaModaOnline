<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [
    'name' => env('APP_NAME', 'SaaS Tienda Moda'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone' => 'America/Lima',
    'locale' => 'es',
    'fallback_locale' => 'en',
    'faker_locale' => 'es_PE',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => ['driver' => 'file'],
    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])->toArray(),
    'aliases' => Facade::defaultAliases()->merge([])->toArray(),
];
