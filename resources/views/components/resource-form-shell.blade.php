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
        <x-back-link :fallback="$backRoute" class="btn-sm" />
    </div>
    {{ $slot }}
</div>
