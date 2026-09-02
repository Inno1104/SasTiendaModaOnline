<form method="POST" action="{{ $action }}" class="customer-form">
    @csrf
    @if($method !== 'POST') @method($method) @endif
    <div class="customer-form-section">
        <div class="customer-form-section-heading"><span><i class="fa-solid fa-id-card"></i></span><div><h2>Información personal</h2><p>Datos principales para identificar al cliente.</p></div></div>
        <div class="customer-form-grid">
            <div class="form-group"><label class="form-label" for="nombre">Nombre *</label><input id="nombre" type="text" name="nombre" class="form-control" maxlength="100" value="{{ old('nombre', $cliente?->nombre) }}" autocomplete="given-name" required>@error('nombre')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="apellido">Apellido *</label><input id="apellido" type="text" name="apellido" class="form-control" maxlength="100" value="{{ old('apellido', $cliente?->apellido) }}" autocomplete="family-name" required>@error('apellido')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="dni">Documento</label><input id="dni" type="text" name="dni" class="form-control" maxlength="30" value="{{ old('dni', $cliente?->dni) }}">@error('dni')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control" max="{{ today()->toDateString() }}" value="{{ old('fecha_nacimiento', $cliente?->fecha_nacimiento?->format('Y-m-d')) }}">@error('fecha_nacimiento')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="genero">Género</label><select id="genero" name="genero" class="form-control"><option value="">No especificado</option><option value="M" @selected(old('genero', $cliente?->genero) === 'M')>Masculino</option><option value="F" @selected(old('genero', $cliente?->genero) === 'F')>Femenino</option><option value="otro" @selected(old('genero', $cliente?->genero) === 'otro')>Otro</option></select>@error('genero')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
        </div>
    </div>
    <div class="customer-form-section">
        <div class="customer-form-section-heading"><span><i class="fa-solid fa-address-book"></i></span><div><h2>Contacto y ubicación</h2><p>Información opcional para comunicaciones y entregas.</p></div></div>
        <div class="customer-form-grid">
            <div class="form-group"><label class="form-label" for="email">Correo electrónico</label><input id="email" type="email" name="email" class="form-control" maxlength="150" value="{{ old('email', $cliente?->email) }}" autocomplete="email">@error('email')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="telefono">Teléfono</label><input id="telefono" type="tel" name="telefono" class="form-control" maxlength="30" value="{{ old('telefono', $cliente?->telefono) }}" autocomplete="tel">@error('telefono')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group"><label class="form-label" for="ciudad">Ciudad</label><input id="ciudad" type="text" name="ciudad" class="form-control" maxlength="100" value="{{ old('ciudad', $cliente?->ciudad) }}" autocomplete="address-level2">@error('ciudad')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
            <div class="form-group customer-form-full"><label class="form-label" for="direccion">Dirección</label><input id="direccion" type="text" name="direccion" class="form-control" maxlength="255" value="{{ old('direccion', $cliente?->direccion) }}" autocomplete="street-address">@error('direccion')<small class="customer-field-error">{{ $message }}</small>@enderror</div>
        </div>
    </div>
    <div class="customer-form-actions"><a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> {{ $submitLabel }}</button></div>
</form>
