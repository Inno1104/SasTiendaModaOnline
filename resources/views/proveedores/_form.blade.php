@php($supplier = $proveedor ?? null)

@if($errors->any())
<div class="alert alert-danger supplier-validation-summary" role="alert" aria-live="polite">
    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
    <div><strong>Revisa los datos ingresados.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
</div>
@endif

<form method="POST" action="{{ $action }}" class="card supplier-form" novalidate>
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <section class="supplier-form-section" aria-labelledby="supplierIdentityTitle">
        <div class="supplier-section-heading"><span><i class="fa-solid fa-building"></i></span><div><h2 id="supplierIdentityTitle">Identificación</h2><p>Información legal utilizada en productos y compras.</p></div></div>
        <div class="supplier-form-grid">
            <label class="supplier-field supplier-field-wide">
                <span>Razón social o nombre <b aria-hidden="true">*</b></span>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $supplier?->nombre) }}" maxlength="200" autocomplete="organization" aria-describedby="nombreHint @error('nombre') nombreError @enderror" required autofocus>
                <small id="nombreHint">Nombre comercial o razón social del proveedor.</small>
                @error('nombre')<small id="nombreError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror
            </label>
            <label class="supplier-field">
                <span>Identificación fiscal</span>
                <input type="text" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc', $supplier?->ruc) }}" maxlength="30" autocomplete="off" aria-describedby="rucHint @error('ruc') rucError @enderror">
                <small id="rucHint">RUC, NIT o identificación equivalente. Puede quedar vacío.</small>
                @error('ruc')<small id="rucError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror
            </label>
            <label class="supplier-field"><span>País</span><input type="text" name="pais" class="form-control @error('pais') is-invalid @enderror" value="{{ old('pais', $supplier?->pais ?? 'Perú') }}" maxlength="100" autocomplete="country-name" @error('pais') aria-describedby="paisError" @enderror>@error('pais')<small id="paisError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
        </div>
    </section>

    <section class="supplier-form-section" aria-labelledby="supplierContactTitle">
        <div class="supplier-section-heading"><span><i class="fa-solid fa-address-card"></i></span><div><h2 id="supplierContactTitle">Contacto</h2><p>Canales para coordinar pedidos y abastecimiento.</p></div></div>
        <div class="supplier-form-grid">
            <label class="supplier-field"><span>Persona de contacto</span><input type="text" name="contacto" class="form-control @error('contacto') is-invalid @enderror" value="{{ old('contacto', $supplier?->contacto) }}" maxlength="150" autocomplete="name" @error('contacto') aria-describedby="contactoError" @enderror>@error('contacto')<small id="contactoError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
            <label class="supplier-field"><span>Correo electrónico</span><input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $supplier?->email) }}" maxlength="255" autocomplete="email" @error('email') aria-describedby="emailError" @enderror>@error('email')<small id="emailError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
            <label class="supplier-field"><span>Teléfono</span><input type="tel" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $supplier?->telefono) }}" maxlength="21" autocomplete="tel" @error('telefono') aria-describedby="telefonoError" @enderror>@error('telefono')<small id="telefonoError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
            <label class="supplier-field"><span>Ciudad</span><input type="text" name="ciudad" class="form-control @error('ciudad') is-invalid @enderror" value="{{ old('ciudad', $supplier?->ciudad) }}" maxlength="100" autocomplete="address-level2" @error('ciudad') aria-describedby="ciudadError" @enderror>@error('ciudad')<small id="ciudadError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
            <label class="supplier-field supplier-field-full"><span>Dirección</span><input type="text" name="direccion" class="form-control @error('direccion') is-invalid @enderror" value="{{ old('direccion', $supplier?->direccion) }}" maxlength="255" autocomplete="street-address" @error('direccion') aria-describedby="direccionError" @enderror>@error('direccion')<small id="direccionError" class="supplier-field-error" role="alert">{{ $message }}</small>@enderror</label>
        </div>
    </section>

    <div class="supplier-form-actions"><a href="{{ route('proveedores.index') }}" class="btn btn-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> {{ $submitLabel }}</button></div>
</form>
