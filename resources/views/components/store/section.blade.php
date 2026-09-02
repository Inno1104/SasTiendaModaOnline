@props(['title', 'description' => null, 'icon' => 'fa-circle-info'])

<section {{ $attributes->class(['store-form-section']) }}>
    <header class="store-section-heading">
        <span class="store-section-icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
        <div>
            <h2>{{ $title }}</h2>
            @if($description)<p>{{ $description }}</p>@endif
        </div>
    </header>
    <div class="store-section-content">{{ $slot }}</div>
</section>
