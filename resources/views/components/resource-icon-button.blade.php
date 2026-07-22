@props([
    'href',
    'icon',
    'label',
    'variant' => 'outline-primary',
])

<a href="{{ $href }}" class="btn btn-{{ $variant }} resource-icon-button" title="{{ $label }}" aria-label="{{ $label }}">
    <i class="fa-solid {{ $icon }}"></i>
</a>
