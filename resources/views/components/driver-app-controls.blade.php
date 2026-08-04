@php
    $pushAvailable = filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
@endphp

<section
    class="driver-app-controls"
    data-driver-app-controls
    data-push-available="{{ $pushAvailable ? '1' : '0' }}"
    data-push-public-key="{{ config('webpush.vapid.public_key') }}"
    data-push-store-url="{{ route('push-subscriptions.store') }}"
    data-push-delete-url="{{ route('push-subscriptions.destroy') }}"
>
    <div class="driver-app-control" data-install-control hidden>
        <span class="driver-app-control-icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></span>
        <div class="driver-app-control-copy">
            <strong>Instalează aplicația pentru șoferi</strong>
            <span>Deschidere rapidă, pe tot ecranul, direct din telefon.</span>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-install-app>Instalează</button>
        <button type="button" class="btn-close" data-dismiss-install aria-label="Închide recomandarea"></button>
    </div>

    <div class="driver-app-control" data-ios-install-control hidden>
        <span class="driver-app-control-icon"><i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i></span>
        <div class="driver-app-control-copy">
            <strong>Adaugă aplicația pe ecranul principal</strong>
            <span>În Safari apasă Partajare, apoi „Adaugă la ecranul principal”.</span>
        </div>
        <button type="button" class="btn-close" data-dismiss-install aria-label="Închide recomandarea"></button>
    </div>

    @if($pushAvailable)
        <div class="driver-app-control" data-push-control>
            <span class="driver-app-control-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>
            <div class="driver-app-control-copy">
                <strong data-push-title>Notificări pentru sarcini</strong>
                <span data-push-message>Primești pe telefon alocările și schimbările importante.</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-toggle-push>Activează</button>
        </div>
    @endif
</section>
