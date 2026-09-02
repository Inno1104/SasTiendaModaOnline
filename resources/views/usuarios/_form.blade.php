@php($formUser = $editar ?? null)
<form method="POST" action="{{ $formUser ? route('usuarios.update', $formUser) : route('usuarios.store') }}" class="user-form" novalidate>
    @csrf
    <div class="user-form-field">
        <label for="user-name">Nombre completo <span aria-hidden="true">*</span></label>
        <input id="user-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $formUser?->name) }}" maxlength="120" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="user-name-error" @enderror>
        @error('name')<small id="user-name-error" class="user-field-error">{{ $message }}</small>@enderror
    </div>

    <div class="user-form-field">
        <label for="user-email">Correo electrónico <span aria-hidden="true">*</span></label>
        <input id="user-email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $formUser?->email) }}" maxlength="254" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="user-email-error" @enderror>
        @error('email')<small id="user-email-error" class="user-field-error">{{ $message }}</small>@enderror
    </div>

    <div class="user-form-row">
        <div class="user-form-field">
            <label for="user-role">Rol <span aria-hidden="true">*</span></label>
            <select id="user-role" name="rol_id" class="form-control @error('rol_id') is-invalid @enderror" required @error('rol_id') aria-invalid="true" aria-describedby="user-role-error" @enderror>
                @foreach($roles as $role)
                    <option value="{{ $role->id }}" @selected((string) old('rol_id', $formUser?->rol_id) === (string) $role->id)>{{ $role->nombre }}</option>
                @endforeach
            </select>
            @error('rol_id')<small id="user-role-error" class="user-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="user-form-field">
            <label for="user-phone">Teléfono</label>
            <input id="user-phone" type="tel" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $formUser?->telefono) }}" maxlength="24" autocomplete="tel" placeholder="Ej.: +593 99 123 4567" @error('telefono') aria-invalid="true" aria-describedby="user-phone-error" @enderror>
            @error('telefono')<small id="user-phone-error" class="user-field-error">{{ $message }}</small>@enderror
        </div>
    </div>

    <div class="user-form-field">
        <label for="user-password">Contraseña @if($formUser)<span class="user-optional">Opcional al editar</span>@else<span aria-hidden="true">*</span>@endif</label>
        <input id="user-password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" maxlength="72" {{ $formUser ? '' : 'required' }} autocomplete="new-password" aria-describedby="user-password-help @error('password') user-password-error @enderror" data-user-password>
        <small id="user-password-help" class="user-field-help">Mínimo 8 caracteres, con letras y números.</small>
        @error('password')<small id="user-password-error" class="user-field-error">{{ $message }}</small>@enderror
    </div>

    <div class="user-form-field">
        <label for="user-password-confirmation">Confirmar contraseña @unless($formUser)<span aria-hidden="true">*</span>@endunless</label>
        <input id="user-password-confirmation" type="password" name="password_confirmation" class="form-control" maxlength="72" {{ $formUser ? '' : 'required' }} autocomplete="new-password" data-user-password-confirmation>
        <small class="user-field-error is-hidden" data-password-mismatch role="alert">Las contraseñas no coinciden.</small>
    </div>

    <div class="user-form-actions">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i><span>{{ $formUser ? 'Guardar cambios' : 'Crear usuario' }}</span></button>
        @if($formUser)<a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar edición</a>@endif
    </div>
</form>
