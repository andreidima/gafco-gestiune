@props(['fallback', 'label' => 'Înapoi'])

<a href="{{ $fallback }}" {{ $attributes->class(['btn', 'btn-outline-secondary'])->merge(['data-smart-back' => '']) }}>
    <i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>{{ $label }}
</a>
