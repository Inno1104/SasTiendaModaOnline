@extends('layouts.app')
@section('title','Mi Tienda')
@section('page-title','Mi Tienda')

@push('styles')<link rel="stylesheet" href="{{ asset('css/tienda.css') }}">@endpush

@section('content')
<div class="page-header store-page-header">
    <div>
        <h1>Mi Tienda</h1>
        <div class="breadcrumb"><a href="{{ route('dashboard') }}">Inicio</a><span class="breadcrumb-sep">›</span>Mi Tienda</div>
    </div>
    <button type="button" class="btn btn-secondary" data-receipt-preview-open><i class="fa-solid fa-eye"></i> Vista previa del comprobante</button>
</div>

<div class="store-layout">
    <!-- Suscripción -->
    <aside class="card store-sidebar">
        <div class="store-identity-card">
            <img class="store-summary-logo" src="{{ $tienda->logo ? asset('storage/'.$tienda->logo) : asset('images/store-logo-default.svg') }}" alt="Logo de {{ $tienda->nombre }}">
            <strong class="store-summary-name">{{ $tienda->nombre }}</strong>
            <span class="store-summary-email">{{ $tienda->email }}</span>
        </div>
        <div class="store-plan-panel" aria-label="Plan y consumo de la tienda">
            @php $b = $subscription['status']; $dr = $subscription['remaining_days']; @endphp
            <dl class="store-plan-facts">
                <div><dt>Plan actual</dt><dd class="store-plan-name">{{ $subscription['plan'] }}</dd></div>
                <div><dt>Estado</dt><dd><span class="badge" style="background:{{ $b[2] }};color:{{ $b[1] }};">{{ $b[0] }}</span></dd></div>
                <div><dt>Vence el</dt><dd>{{ $subscription['expires_at']?->format('d/m/Y') ?? 'Sin vencimiento' }}</dd></div>
                <div><dt>Días restantes</dt><dd class="{{ $dr !== null && $dr <= 5 ? 'is-critical' : '' }}">{{ $dr !== null ? $dr.' días' : 'No aplica' }}</dd></div>
            </dl>
            <div class="store-plan-usage">
                <h3>Uso del plan</h3>
                @foreach(['products' => ['Productos activos','fa-box'], 'users' => ['Usuarios registrados','fa-users']] as $resource => [$label,$icon])
                    @php $usage = $subscription[$resource]; @endphp
                    <section class="store-usage store-usage--{{ $usage['state'] }}">
                        <div class="store-usage-heading"><span><i class="fa-solid {{ $icon }}"></i> {{ $label }}</span><strong>{{ $usage['used'] }} / {{ $usage['unlimited'] ? 'Ilimitado' : ($usage['limit'] ?? 'N/D') }}</strong></div>
                        @if($usage['unlimited'])
                            <div class="store-unlimited"><i class="fa-solid fa-infinity"></i> Sin límite contratado</div>
                        @elseif($usage['limit'] !== null)
                            <div class="store-progress" role="progressbar" aria-label="Consumo de {{ mb_strtolower($label) }}" aria-valuenow="{{ $usage['used'] }}" aria-valuemin="0" aria-valuemax="{{ $usage['limit'] }}"><span style="width:{{ $usage['percentage'] }}%"></span></div>
                            <small>{{ $usage['state'] === 'reached' ? 'Límite alcanzado. Contacta al superadministrador.' : ($usage['state'] === 'warning' ? 'Cerca del límite: quedan '.$usage['remaining'].'.' : 'Disponibles: '.$usage['remaining'].'.') }}</small>
                        @else
                            <small>El plan no tiene un límite configurado.</small>
                        @endif
                    </section>
                @endforeach
            </div>
            <p class="store-plan-readonly"><i class="fa-solid fa-lock"></i> Plan, estado, vencimiento y límites son administrados únicamente por el superadministrador.</p>
        </div>
    </aside>

    <!-- Datos de la tienda -->
    <div class="card store-main-card">
        <div class="card-header store-card-header"><div><span class="store-eyebrow">Identidad comercial</span><div class="card-title"><i class="fa-solid fa-store"></i> Información de la tienda</div><p>Actualiza los datos que se muestran en comprobantes y canales públicos.</p></div></div>
        @if($errors->any())<div class="alert alert-danger store-error-summary" role="alert" aria-live="assertive" tabindex="-1" data-error-summary><strong>Revisa los campos indicados.</strong><span>Encontramos {{ $errors->count() }} {{ $errors->count() === 1 ? 'error' : 'errores' }} en el formulario.</span></div>@endif
        <form method="POST" action="{{ route('tienda.update') }}" enctype="multipart/form-data" data-store-profile-form>
            @csrf
            <x-store.section title="Logo de la tienda" description="Usa una imagen cuadrada y legible para conservar una identidad consistente." icon="fa-image">
            <section class="store-logo-editor" aria-labelledby="store-logo-title">
                <img data-store-logo-preview data-default-src="{{ asset('images/store-logo-default.svg') }}" class="store-logo-preview" src="{{ $tienda->logo ? asset('storage/'.$tienda->logo) : asset('images/store-logo-default.svg') }}" alt="Vista previa del logo">
                <div>
                    <strong id="store-logo-title">Logo e identidad visual</strong>
                    <span class="store-logo-help">PNG, JPG, JPEG, WebP o SVG seguro. Máximo 3 MB; tamaño recomendado 500×500 px.</span>
                    <div class="store-logo-actions">
                        <label class="btn btn-secondary" for="store-logo"><i class="fa-solid fa-cloud-arrow-up"></i> {{ $tienda->logo ? 'Reemplazar logo' : 'Seleccionar logo' }}</label>
                        <input data-store-logo-input id="store-logo" type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" @error('logo') aria-invalid="true" aria-describedby="store-logo-error" @enderror>
                        @if($tienda->logo)<label class="store-logo-remove"><input data-store-logo-remove type="checkbox" name="eliminar_logo" value="1" @checked(old('eliminar_logo'))> Eliminar logo actual</label>@else<input data-store-logo-remove type="checkbox" name="eliminar_logo" value="1" hidden>@endif
                    </div>
                    <div data-store-logo-feedback class="store-logo-feedback" id="store-logo-error" aria-live="polite">@error('logo'){{ $message }}@enderror</div>
                </div>
            </section>
            </x-store.section>
            <x-store.section title="Identidad y contacto" description="Información principal para clientes y comprobantes." icon="fa-address-card">
            <div class="form-group"><label class="form-label" for="store-name">Nombre comercial</label><input id="store-name" type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $tienda->nombre) }}" maxlength="120" required @error('nombre') aria-invalid="true" aria-describedby="store-name-error" @enderror>@error('nombre')<small id="store-name-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
            <div class="form-row">
                <div class="form-group"><label class="form-label" for="store-tax-id">RUC / Identificación fiscal</label><input id="store-tax-id" type="text" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc', $tienda->ruc) }}" maxlength="20" placeholder="Opcional" @error('ruc') aria-invalid="true" aria-describedby="store-tax-id-error" @enderror>@error('ruc')<small id="store-tax-id-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label class="form-label" for="store-email">Correo de contacto</label><input id="store-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $tienda->email) }}" maxlength="254" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="store-email-error" @enderror>@error('email')<small id="store-email-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
            </div>
            <div class="form-row">
                <div class="form-group"><label class="form-label" for="store-phone">Teléfono</label><input id="store-phone" type="tel" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $tienda->telefono) }}" placeholder="+593 99 123 4567" @error('telefono') aria-invalid="true" aria-describedby="store-phone-error" @enderror>@error('telefono')<small id="store-phone-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label class="form-label" for="store-whatsapp">WhatsApp</label><input id="store-whatsapp" type="tel" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp', $tienda->whatsapp) }}" placeholder="+593 99 123 4567" @error('whatsapp') aria-invalid="true" aria-describedby="store-whatsapp-error" @enderror>@error('whatsapp')<small id="store-whatsapp-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
            </div>
            <div class="form-group"><label class="form-label" for="store-address">Dirección</label><input id="store-address" type="text" name="direccion" class="form-control @error('direccion') is-invalid @enderror" value="{{ old('direccion', $tienda->direccion) }}" maxlength="255" placeholder="Opcional" @error('direccion') aria-invalid="true" aria-describedby="store-address-error" @enderror><x-store.field-error name="direccion" id="store-address-error" /></div>
            </x-store.section>
            <x-store.section title="Ubicación" description="Mantén estos datos consistentes para futuras ventas y publicaciones en línea." icon="fa-location-dot">
            <div class="form-row">
                <div class="form-group"><label class="form-label" for="store-country">País</label><select id="store-country" name="pais_codigo" class="form-control @error('pais_codigo') is-invalid @enderror" @error('pais_codigo') aria-invalid="true" aria-describedby="store-country-error" @enderror><option value="">Seleccionar</option>@foreach($countries as $code => $country)<option value="{{ $code }}" @selected(old('pais_codigo', $tienda->pais_codigo) === $code)>{{ $country }}</option>@endforeach</select>@error('pais_codigo')<small id="store-country-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label class="form-label" for="store-province">Provincia / Estado</label><input id="store-province" type="text" name="provincia" class="form-control @error('provincia') is-invalid @enderror" value="{{ old('provincia', $tienda->provincia) }}" maxlength="100" @error('provincia') aria-invalid="true" aria-describedby="store-province-error" @enderror>@error('provincia')<small id="store-province-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
                <div class="form-group"><label class="form-label" for="store-city">Ciudad / Cantón</label><input id="store-city" type="text" name="ciudad" class="form-control @error('ciudad') is-invalid @enderror" value="{{ old('ciudad', $tienda->ciudad) }}" maxlength="100" @error('ciudad') aria-invalid="true" aria-describedby="store-city-error" @enderror>@error('ciudad')<small id="store-city-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
            </div>
            </x-store.section>
            <x-store.section title="Presencia digital" description="Enlaces públicos opcionales. Solo se permiten direcciones HTTPS válidas." icon="fa-globe">
            <div class="form-group"><label class="form-label" for="store-website">Sitio web</label><input id="store-website" type="url" name="sitio_web" class="form-control @error('sitio_web') is-invalid @enderror" value="{{ old('sitio_web', $tienda->sitio_web) }}" maxlength="2048" placeholder="https://www.tutienda.com" @error('sitio_web') aria-invalid="true" aria-describedby="store-website-error" @enderror><x-store.field-error name="sitio_web" id="store-website-error" /></div>
            <div class="form-row">
                @foreach(['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok'] as $network => $label)
                <div class="form-group"><label class="form-label" for="store-social-{{ $network }}">{{ $label }}</label><input id="store-social-{{ $network }}" type="url" name="redes_sociales[{{ $network }}]" class="form-control @error('redes_sociales.'.$network) is-invalid @enderror" value="{{ old('redes_sociales.'.$network, data_get($tienda->redes_sociales, $network)) }}" maxlength="2048" placeholder="https://{{ $network }}.com/tuusuario" @error('redes_sociales.'.$network) aria-invalid="true" aria-describedby="store-social-{{ $network }}-error" @enderror>@error('redes_sociales.'.$network)<small id="store-social-{{ $network }}-error" style="color:#dc2626;">{{ $message }}</small>@enderror</div>
                @endforeach
            </div>
            </x-store.section>
            <div class="store-form-actions"><span><i class="fa-solid fa-shield-halved"></i> Tus cambios se validan y auditan de forma segura.</span><button type="submit" class="btn btn-primary" data-submit-button><i class="fa-solid fa-floppy-disk" data-submit-icon></i><span data-submit-label>Guardar cambios</span></button></div>
        </form>
    </div>
</div>

<section class="card store-publication-card" aria-labelledby="storefront-settings-title">
    <div class="card-header store-card-header">
        <div>
            <span class="store-eyebrow">Canal online</span>
            <div class="card-title" id="storefront-settings-title"><i class="fa-solid fa-store"></i> Tienda pública</div>
            <p>Controla la información visible en <strong>/tienda/{{ $tienda->slug }}</strong>. El enlace no cambia al modificar el nombre comercial.</p>
        </div>
        @if($tienda->publicada)
            <a class="btn btn-secondary" href="{{ $tienda->publicUrl() }}" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver tienda pública</a>
        @endif
    </div>

    <form method="POST" action="{{ route('tienda.storefront.update') }}" data-store-profile-form>
        @csrf
        <x-store.section title="Publicación e identidad" description="Puedes preparar la página y publicarla cuando toda la información esté lista." icon="fa-bullhorn">
            <div class="storefront-publish-row">
                <label class="storefront-switch">
                    <input type="checkbox" name="publicada" value="1" @checked(old('publicada', $tienda->publicada))>
                    <span aria-hidden="true"></span>
                    <strong>Publicar tienda online</strong>
                    <small>Si está desactivada, la dirección pública responderá como no disponible.</small>
                </label>
                <div class="form-group storefront-color-field">
                    <label class="form-label" for="storefront-color">Color principal</label>
                    <input id="storefront-color" type="color" name="color_primario" value="{{ old('color_primario', $tienda->color_primario ?: '#E8398C') }}" aria-describedby="storefront-color-help">
                    <small id="storefront-color-help">Se aplica a botones y elementos destacados.</small>
                    <x-store.field-error name="color_primario" id="storefront-color-error" />
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="storefront-description">Descripción pública</label>
                <textarea id="storefront-description" name="descripcion_publica" class="form-control @error('descripcion_publica') is-invalid @enderror" rows="3" maxlength="500" placeholder="Describe brevemente tu tienda y propuesta de valor.">{{ old('descripcion_publica', $tienda->descripcion_publica) }}</textarea>
                <x-store.field-error name="descripcion_publica" id="storefront-description-error" />
            </div>
        </x-store.section>

        <x-store.section title="Horarios de atención" description="Indica cuándo pueden contactarte o retirar pedidos." icon="fa-clock">
            <div class="storefront-schedule">
                @foreach($storefrontDays as $day => $label)
                    @php
                        $schedule = old('horarios.'.$day, data_get($tienda->horarios, $day, ['activo' => in_array($day, ['lunes','martes','miercoles','jueves','viernes'], true), 'apertura' => '09:00', 'cierre' => '18:00']));
                        $active = filter_var(data_get($schedule, 'activo', false), FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <div class="storefront-schedule-row">
                        <label class="storefront-day"><input type="checkbox" name="horarios[{{ $day }}][activo]" value="1" @checked($active)> <span>{{ $label }}</span></label>
                        <label><span class="sr-only">Apertura {{ $label }}</span><input type="time" name="horarios[{{ $day }}][apertura]" class="form-control" value="{{ data_get($schedule, 'apertura') }}"></label>
                        <span aria-hidden="true">a</span>
                        <label><span class="sr-only">Cierre {{ $label }}</span><input type="time" name="horarios[{{ $day }}][cierre]" class="form-control" value="{{ data_get($schedule, 'cierre') }}"></label>
                        @error('horarios.'.$day.'.apertura')<small class="storefront-row-error">{{ $message }}</small>@enderror
                        @error('horarios.'.$day.'.cierre')<small class="storefront-row-error">{{ $message }}</small>@enderror
                    </div>
                @endforeach
            </div>
        </x-store.section>

        <x-store.section title="Condiciones comerciales" description="Comunica con claridad las reglas de compra antes de habilitar el checkout." icon="fa-file-shield">
            <div class="storefront-conditions-grid">
                @foreach($storefrontConditionLabels as $field => $label)
                    <div class="form-group">
                        <label class="form-label" for="storefront-condition-{{ $field }}">{{ $label }}</label>
                        <textarea id="storefront-condition-{{ $field }}" name="condiciones_venta[{{ $field }}]" class="form-control @error('condiciones_venta.'.$field) is-invalid @enderror" rows="4" maxlength="3000" placeholder="Información opcional para tus clientes.">{{ old('condiciones_venta.'.$field, data_get($tienda->condiciones_venta, $field)) }}</textarea>
                        @error('condiciones_venta.'.$field)<small class="storefront-row-error">{{ $message }}</small>@enderror
                    </div>
                @endforeach
            </div>
        </x-store.section>

        <div class="store-form-actions">
            <span><i class="fa-solid fa-shield-halved"></i> Solo se publican los datos comerciales configurados en esta página.</span>
            <button type="submit" class="btn btn-primary" data-submit-button><i class="fa-solid fa-floppy-disk" data-submit-icon></i><span data-submit-label>Guardar publicación</span></button>
        </div>
    </form>
</section>
@include('tienda.partials.receipt-preview')
<dialog class="store-confirm-dialog" data-logo-confirm aria-labelledby="logo-confirm-title" aria-describedby="logo-confirm-description">
    <div class="store-confirm-icon"><i class="fa-solid fa-trash-can"></i></div>
    <h2 id="logo-confirm-title">¿Eliminar el logo actual?</h2>
    <p id="logo-confirm-description">La tienda utilizará la imagen predeterminada. El cambio solo será definitivo cuando guardes el formulario.</p>
    <div class="store-confirm-actions"><button type="button" class="btn btn-secondary" data-logo-cancel>Cancelar</button><button type="button" class="btn btn-danger" data-logo-accept>Eliminar logo</button></div>
</dialog>
@endsection
@push('scripts')<script src="{{ asset('js/tienda.js') }}" defer></script>@endpush
