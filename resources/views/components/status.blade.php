@props(['status', 'href' => null])

@php
    $labels = [
        'draft' => 'Draft',
        'pending_approval' => 'Asteapta aprobare',
        'pending' => 'In asteptare',
        'pending_acceptance' => 'Asteapta soferul',
        'unassigned' => 'Nealocat',
        'approved' => 'Aprobat',
        'created' => 'Creat',
        'open' => 'Deschis',
        'assigned' => 'Alocat',
        'in_progress' => 'In lucru',
        'in_transit' => 'In tranzit',
        'received' => 'Receptionat',
        'posted' => 'Inregistrat',
        'modified' => 'Modificat',
        'accepted' => 'Acceptat',
        'completed' => 'Finalizat',
        'closed' => 'Inchis',
        'cancelled' => 'Anulat',
        'rejected' => 'Refuzat',
        'reassignment_requested' => 'Realocare solicitata',
        'archived' => 'Arhivat',
        'expired' => 'Expirat',
    ];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class(['status-badge', 'status-badge-link', 'status-'.$status]) }}>
        {{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
        <span class="visually-hidden"> — deschide înregistrarea</span>
    </a>
@else
    <span {{ $attributes->class(['status-badge', 'status-'.$status]) }}>
        {{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
    </span>
@endif
