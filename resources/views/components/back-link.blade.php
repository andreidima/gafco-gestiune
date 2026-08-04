@props(['fallback', 'label' => 'Înapoi', 'smart' => true])

<a href="{{ $fallback }}" {{ $attributes->class(['btn', 'btn-outline-secondary']) }} @if($smart) data-smart-back @endif>
    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>{{ $label }}
</a>
