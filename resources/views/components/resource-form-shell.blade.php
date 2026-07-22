@props([
    'title',
    'description' => null,
    'backRoute',
    'icon' => 'fa-pen-to-square',
])

<div class="resource-form-shell">
    <div class="resource-form-header">
        <div class="resource-page-heading">
            <span class="resource-page-icon"><i class="fa-solid {{ $icon }}"></i></span>
            <div>
                <h1>{{ $title }}</h1>
                @if($description)<p>{{ $description }}</p>@endif
            </div>
        </div>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i>Inapoi
        </a>
    </div>
    {{ $slot }}
</div>
