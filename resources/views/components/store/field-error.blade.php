@props(['name', 'id' => null])

@error($name)
    <small id="{{ $id ?? str_replace('.', '-', $name).'-error' }}" class="store-field-error" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}
    </small>
@enderror
