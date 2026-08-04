@props(['source' => null, 'destination' => null, 'compact' => false])

@php
    $origin = $source?->address ?: $source?->name;
    $target = $destination?->address ?: $destination?->name;
    $mapsUrl = $target
        ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($target).($origin ? '&origin='.rawurlencode($origin) : '')
        : null;
    $wazeUrl = $target ? 'https://www.waze.com/ul?q='.rawurlencode($target).'&navigate=yes' : null;
@endphp

@if($mapsUrl)
    <div {{ $attributes->class(['driver-route-actions', 'driver-route-actions-compact' => $compact]) }}>
        <a href="{{ $mapsUrl }}" class="btn btn-outline-primary {{ $compact ? 'btn-sm' : '' }}" target="_blank" rel="noopener">
            <i class="fa-solid fa-map-location-dot me-1" aria-hidden="true"></i>Traseu
        </a>
        <a href="{{ $wazeUrl }}" class="btn btn-outline-secondary {{ $compact ? 'btn-sm' : '' }}" target="_blank" rel="noopener">
            <i class="fa-brands fa-waze me-1" aria-hidden="true"></i>Waze
        </a>
    </div>
@endif
