@props(['status'])

@php
    $labels = [
        'draft' => 'Draft',
        'pending_approval' => 'Asteapta aprobare',
        'approved' => 'Aprobat',
        'open' => 'Deschis',
        'assigned' => 'Alocat',
        'in_progress' => 'In lucru',
        'in_transit' => 'In tranzit',
        'received' => 'Receptionat',
        'posted' => 'Inregistrat',
        'closed' => 'Inchis',
        'cancelled' => 'Anulat',
    ];
@endphp

<span {{ $attributes->class(['status-badge', 'status-'.$status]) }}>
    {{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
</span>
