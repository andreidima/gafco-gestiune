<!doctype html>
<html class="h-100" lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#703b3b">
    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>
    @if(auth()->check() && auth()->user()->usesDriverWorkspace())
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="apple-touch-icon" href="{{ asset('icons/gafco-driver-192.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="GAFCO Șofer">
    @endif
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    @vite('resources/js/app.js')
</head>
<body @class(['d-flex', 'flex-column', 'h-100', 'driver-workspace' => auth()->check() && auth()->user()->usesDriverWorkspace()])>
    @auth
        @php
            $navigationUser = auth()->user();
            $navigationOperations = $navigationUser->isOperationsAdmin();
            $navigationManager = $navigationUser->hasAnyRole(['sef-santier', 'gestionar-baza']);
            $navigationDriver = $navigationUser->usesDriverWorkspace();
            $navigationWorker = $navigationUser->usesWorkerWorkspace();
            $navigationAccounting = $navigationUser->hasRole('contabil');
            $navigationManagement = $navigationUser->isManagementUser();
            $notificationsAvailable = \Illuminate\Support\Facades\Schema::hasTable('notifications');
            $receptionWorkflowAvailable = \Illuminate\Support\Facades\Schema::hasTable('reception_intakes');
            $negotiatedOrdersAvailable = \Illuminate\Support\Facades\Schema::hasTable('negotiated_orders');
            $projectsAvailable = \Illuminate\Support\Facades\Schema::hasTable('projects');
            $alertsAvailable = \Illuminate\Support\Facades\Schema::hasTable('operational_alerts')
                && \Illuminate\Support\Facades\Schema::hasTable('operational_alert_user');
            $navigationCanViewAlerts = $alertsAvailable
                && app(\App\Services\OperationalAlertAccessService::class)->canUse($navigationUser);
            $navigationActiveAlerts = $navigationCanViewAlerts
                ? app(\App\Services\OperationalAlertAccessService::class)->visibleAlerts($navigationUser)->active()->count()
                : 0;
            $impersonationContext = app(\App\Support\ImpersonationContext::class);
            $isImpersonating = $impersonationContext->isActive();
            $impersonationActor = $impersonationContext->actor();
            $impersonationAvailable = $impersonationActor?->active
                && $impersonationActor->can(\App\Support\ImpersonationContext::PERMISSION);
        @endphp
        <header>
            @if($navigationDriver)
                <div class="driver-mobile-topbar">
                    <a href="{{ route('dashboard') }}" class="driver-mobile-brand" aria-label="Acasă">
                        <img src="{{ asset('icons/gafco-driver-192.png') }}" alt="" width="36" height="36">
                        <span><strong>GAFCO</strong><small>Șofer</small></span>
                    </a>
                    <div class="driver-mobile-topbar-actions">
                        <span class="driver-network-state" data-network-state><i class="fa-solid fa-signal" aria-hidden="true"></i><span>Online</span></span>
                        <a href="{{ route('notifications.index') }}" class="driver-mobile-icon-button" aria-label="Notificări">
                            <i class="fa-solid fa-bell" aria-hidden="true"></i>
                            @if($notificationsAvailable && auth()->user()->unreadNotifications()->count())
                                <span class="driver-mobile-badge">{{ auth()->user()->unreadNotifications()->count() }}</span>
                            @endif
                        </a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="driver-mobile-icon-button" aria-label="Deconectare"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </div>
            @endif
            <nav class="navbar navbar-lg navbar-expand-lg navbar-dark shadow culoare1 driver-desktop-navbar">
                <div class="container">
                    <a class="navbar-brand me-4 fw-bold" href="{{ route('dashboard') }}">
                        {{ config('app.name') }}
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Meniu">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="mainNavbar">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                    <i class="fa-solid fa-house me-1"></i> Acasa
                                </a>
                            </li>
                            @if($navigationManagement || $navigationAccounting)
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('locations.*', 'catalog-items.*', 'suppliers.*', 'inventory.*', 'tracked-assets.*', 'projects.*', 'reception-intakes.*', 'supplier-receptions.*', 'negotiated-orders.*', 'consumption-reports.*', 'returns.*', 'alerts.*', 'field.worker') ? 'active' : '' }}" href="#" id="gestiuneDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i> Gestiune
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="gestiuneDropdown">
                                        @if($navigationManagement && $receptionWorkflowAvailable)
                                            <li><a class="dropdown-item {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}">Locatii</a></li>
                                            <li><a class="dropdown-item {{ request()->routeIs('tracked-assets.*') ? 'active' : '' }}" href="{{ route('tracked-assets.index') }}">Echipamente</a></li>
                                            <li><a class="dropdown-item {{ request()->routeIs('catalog-items.*') ? 'active' : '' }}" href="{{ route('catalog-items.index') }}">Nomenclator</a></li>
                                        @endif
                                        <li><a class="dropdown-item {{ request()->routeIs('suppliers.*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">Furnizori</a></li>
                                        @if($navigationManagement && $projectsAvailable)
                                            <li><a class="dropdown-item {{ request()->routeIs('projects.*') ? 'active' : '' }}" href="{{ route('projects.index') }}">Proiecte materiale</a></li>
                                        @endif
                                        <li><a class="dropdown-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">Fișă inventar materiale</a></li>
                                        @if($navigationCanViewAlerts)
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 {{ request()->routeIs('alerts.*') ? 'active' : '' }}" href="{{ route('alerts.index') }}">
                                                    <span>Alerte</span>
                                                    @if($navigationActiveAlerts)
                                                        <span class="badge rounded-pill text-bg-danger">{{ $navigationActiveAlerts }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endif
                                        @if($navigationManagement && $receptionWorkflowAvailable)
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center justify-content-between gap-3 {{ request()->routeIs('reception-intakes.*') ? 'active' : '' }}" href="{{ route('reception-intakes.index') }}">
                                                    <span>Documente de procesat</span>
                                                    @php
                                                        $navigationOpenReceptionIntakes = app(\App\Services\ReceptionAccessService::class)
                                                            ->visibleIntakes($navigationUser)
                                                            ->where('status', 'created')
                                                            ->count();
                                                    @endphp
                                                    @if($navigationOpenReceptionIntakes)
                                                        <span class="badge rounded-pill text-bg-warning">{{ $navigationOpenReceptionIntakes }}</span>
                                                    @endif
                                                </a>
                                            </li>
                                        @endif
                                        <li><a class="dropdown-item {{ request()->routeIs('supplier-receptions.*') ? 'active' : '' }}" href="{{ route('supplier-receptions.index') }}">Recepții</a></li>
                                        @if($navigationUser->hasAnyRole(['admin', 'super-admin']) && $negotiatedOrdersAvailable)
                                            <li><a class="dropdown-item {{ request()->routeIs('negotiated-orders.*') ? 'active' : '' }}" href="{{ route('negotiated-orders.index') }}">Comenzi negociate</a></li>
                                        @endif
                                        <li><a class="dropdown-item {{ request()->routeIs('consumption-reports.*') ? 'active' : '' }}" href="{{ route('consumption-reports.index') }}">Consum</a></li>
                                        @if($navigationManagement)<li><a class="dropdown-item {{ request()->routeIs('field.worker') ? 'active' : '' }}" href="{{ route('field.worker') }}">Custodie personală</a></li>@endif
                                        @if($navigationManagement)<li><a class="dropdown-item {{ request('purpose') === 'return' ? 'active' : '' }}" href="{{ route('transfers.index', ['purpose' => 'return']) }}">Retururi</a></li>@endif
                                    </ul>
                                </li>
                            @endif

                            @if($navigationManagement)
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('transfers.*', 'tasks.*', 'driver-requests.*') ? 'active' : '' }}" href="#" id="transferuriDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-right-left me-1"></i> Operatiuni
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="transferuriDropdown">
                                        <li><a class="dropdown-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}" href="{{ route('transfers.index') }}">Transferuri</a></li>
                                        <li><a class="dropdown-item {{ request()->routeIs('tasks.index', 'tasks.show', 'tasks.create', 'tasks.edit') ? 'active' : '' }}" href="{{ route('tasks.index') }}">Sarcini soferi</a></li>
                                        @can('create', \App\Models\Task::class)
                                            <li><a class="dropdown-item {{ request()->routeIs('tasks.dispatch') ? 'active' : '' }}" href="{{ route('tasks.dispatch') }}">Situatie soferi</a></li>
                                        @endcan
                                    </ul>
                                </li>
                            @elseif($navigationDriver)
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('tasks.*') ? 'active' : '' }}" href="{{ route('tasks.index') }}"><i class="fa-solid fa-list-check me-1"></i>Sarcinile mele</a></li>
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}" href="{{ route('transfers.index') }}"><i class="fa-solid fa-right-left me-1"></i>Transferurile mele</a></li>
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('field.worker') ? 'active' : '' }}" href="{{ route('field.worker') }}"><i class="fa-solid fa-hand-holding-hand me-1"></i>Custodia mea</a></li>
                            @endif

                            @if($navigationOperations)
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('field.*', 'qr-scan.*') ? 'active' : '' }}" href="#" id="terenDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-mobile-screen-button me-1"></i>Teren</a>
                                    <ul class="dropdown-menu" aria-labelledby="terenDropdown">
                                        <li><a class="dropdown-item {{ request()->routeIs('field.site-manager') ? 'active' : '' }}" href="{{ route('field.site-manager') }}">Sef santier</a></li>
                                        <li><a class="dropdown-item {{ request()->routeIs('field.worker') ? 'active' : '' }}" href="{{ route('field.worker') }}">Custodie personală</a></li>
                                        <li><a class="dropdown-item {{ request()->routeIs('qr-scan.*') ? 'active' : '' }}" href="{{ route('qr-scan.index') }}">Scanare QR</a></li>
                                    </ul>
                                </li>
                            @elseif($navigationManager)
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('field.site-manager') ? 'active' : '' }}" href="{{ route('field.site-manager') }}"><i class="fa-solid fa-mobile-screen-button me-1"></i>Teren</a></li>
                            @elseif($navigationWorker)
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('field.worker') ? 'active' : '' }}" href="{{ route('field.worker') }}"><i class="fa-solid fa-hand-holding-hand me-1"></i>Custodia mea</a></li>
                                @if($receptionWorkflowAvailable)
                                @can('reception-documents.upload')
                                    <li class="nav-item me-2">
                                        <a class="nav-link {{ request()->routeIs('reception-intakes.*') ? 'active' : '' }}" href="{{ route('reception-intakes.create') }}">
                                            <i class="fa-solid fa-camera me-1"></i>Document recepție
                                        </a>
                                    </li>
                                @endcan
                                @endif
                            @endif

                            @if($navigationDriver || $navigationWorker)
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('qr-scan.*') ? 'active' : '' }}" href="{{ route('qr-scan.index') }}"><i class="fa-solid fa-qrcode me-1"></i>QR</a></li>
                            @endif

                            @if($navigationManagement || $navigationAccounting)
                                <li class="nav-item me-2"><a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="fa-solid fa-chart-column me-1"></i>Rapoarte</a></li>
                            @endif

                            @if($navigationUser->hasAnyRole(['admin','super-admin']) || $navigationUser->can('access.view'))
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*', 'access.*', 'alert-rules.*') ? 'active' : '' }}" href="#" id="utileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-gear me-1"></i> Setări
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="utileDropdown">
                                        @if($navigationUser->hasAnyRole(['admin','super-admin']))
                                            <li>
                                                <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                                    Utilizatori
                                                </a>
                                            </li>
                                        @endif
                                        @can('access.view')
                                            <li>
                                                <a class="dropdown-item {{ request()->routeIs('access.*') ? 'active' : '' }}" href="{{ route('access.index') }}">
                                                    Administrare acces
                                                </a>
                                            </li>
                                        @endcan
                                        @if($alertsAvailable)
                                            <li>
                                                <a class="dropdown-item {{ request()->routeIs('alert-rules.*') ? 'active' : '' }}" href="{{ route('alert-rules.index') }}">
                                                    Reguli de alertare
                                                </a>
                                            </li>
                                        @endif
                                        @can('access-database-tools')
                                            @unless($isImpersonating)
                                                <li>
                                                    <a class="dropdown-item {{ request()->routeIs('system.database*') ? 'active' : '' }}" href="{{ route('system.database') }}">
                                                        Baza de date si migrari
                                                    </a>
                                                </li>
                                            @endunless
                                        @endcan
                                    </ul>
                                </li>
                            @endif
                        </ul>

                        <ul class="navbar-nav ms-auto">
                            @if($impersonationAvailable)
                                <li class="nav-item me-2">
                                    <button
                                        type="button"
                                        class="nav-link text-white border-0 bg-transparent"
                                        data-bs-toggle="modal"
                                        data-bs-target="#impersonationModal"
                                        aria-label="Schimbă utilizatorul"
                                        title="Schimbă utilizatorul"
                                    >
                                        <i class="fa-solid fa-user-secret"></i>
                                        <span class="d-lg-none ms-1">Schimbă utilizatorul</span>
                                    </button>
                                </li>
                            @endif
                            <li class="nav-item dropdown me-2">
                                <a
                                    class="nav-link dropdown-toggle text-white {{ request()->routeIs('help.*', 'release-notes.*') ? 'active' : '' }}"
                                    href="#"
                                    id="helpDropdown"
                                    role="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    aria-label="Ajutor și noutăți"
                                    title="Ajutor și noutăți"
                                >
                                    <i class="fa-solid fa-circle-question"></i>
                                    <span class="d-lg-none ms-1">Ajutor</span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="helpDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('help.*') ? 'active' : '' }}" href="{{ route('help.index') }}">
                                            <i class="fa-solid fa-book-open me-2"></i>Centru de ajutor
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('release-notes.*') ? 'active' : '' }}" href="{{ route('release-notes.index') }}">
                                            <i class="fa-solid fa-bullhorn me-2"></i>Noutăți
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            @if($notificationsAvailable)
                            <li class="nav-item dropdown me-2">
                                <a class="nav-link dropdown-toggle text-white" href="#" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notificari">
                                    <i class="fa-solid fa-bell"></i>
                                    @if(auth()->user()->unreadNotifications()->count())
                                        <span class="badge text-bg-danger">{{ auth()->user()->unreadNotifications()->count() }}</span>
                                    @endif
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 340px">
                                    @forelse(auth()->user()->notifications()->latest()->limit(6)->get() as $notification)
                                        <li>
                                            <form method="post" action="{{ route('notifications.read', $notification->id) }}">
                                                @csrf
                                                <button class="dropdown-item text-wrap {{ $notification->read_at ? '' : 'fw-bold' }}">
                                                    <span class="d-block">{{ $notification->data['title'] ?? 'Notificare' }}</span>
                                                    <span class="small text-muted">{{ $notification->data['message'] ?? '' }}</span>
                                                </button>
                                            </form>
                                        </li>
                                    @empty
                                        <li><span class="dropdown-item-text text-muted">Nu exista notificari.</span></li>
                                    @endforelse
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="{{ route('notifications.index') }}">Vezi toate notificarile</a></li>
                                    @if(auth()->user()->unreadNotifications()->exists())
                                        <li><form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="dropdown-item text-center">Marcheaza toate ca citite</button></form></li>
                                    @endif
                                </ul>
                            </li>
                            @endif
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-user me-1"></i> {{ auth()->user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li>
                                        <form method="post" action="{{ route('logout') }}">
                                            @csrf
                                            <button class="dropdown-item text-danger">
                                                <i class="fa-solid fa-sign-out-alt me-1"></i> Deconectare
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            @if($isImpersonating)
                <div class="impersonation-banner" role="status">
                    <div class="container impersonation-banner-inner">
                        <div class="impersonation-banner-copy">
                            <i class="fa-solid fa-user-secret" aria-hidden="true"></i>
                            <span>
                                Vizualizezi aplicația ca
                                <strong>{{ $navigationUser->name }}</strong>
                                @if($navigationUser->roles->isNotEmpty())
                                    <span class="impersonation-banner-role">
                                        ({{ $navigationUser->roles->map(fn ($role) => config('roles.labels.'.$role->name, $role->name))->join(', ') }})
                                    </span>
                                @endif
                                <span class="d-block d-md-inline">
                                    Administrator: <strong>{{ $impersonationActor?->name }}</strong>.
                                </span>
                            </span>
                        </div>
                        <div class="impersonation-banner-actions">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-dark"
                                data-bs-toggle="modal"
                                data-bs-target="#impersonationModal"
                            >
                                <i class="fa-solid fa-repeat me-1"></i>Schimbă
                            </button>
                            <form method="post" action="{{ route('impersonation.stop') }}">
                                @csrf
                                <button class="btn btn-sm btn-dark">
                                    <i class="fa-solid fa-arrow-right-from-bracket me-1"></i>Revino la contul meu
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </header>

        @if($navigationDriver)
            <div class="driver-offline-banner" data-offline-banner hidden>
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>Ești offline. Acțiunile sunt blocate până revine conexiunea.</span>
            </div>
            <nav class="driver-bottom-nav" aria-label="Navigare șofer">
                <a href="{{ route('tasks.index') }}" class="{{ request()->routeIs('tasks.*') ? 'active' : '' }}"><i class="fa-solid fa-list-check" aria-hidden="true"></i><span>Sarcini</span></a>
                <a href="{{ route('transfers.index') }}" class="{{ request()->routeIs('transfers.*') ? 'active' : '' }}"><i class="fa-solid fa-right-left" aria-hidden="true"></i><span>Transferuri</span></a>
                <a href="{{ route('qr-scan.index') }}" class="driver-bottom-nav-primary {{ request()->routeIs('qr-scan.*') ? 'active' : '' }}"><i class="fa-solid fa-qrcode" aria-hidden="true"></i><span>QR</span></a>
                <a href="{{ route('field.worker') }}" class="{{ request()->routeIs('field.worker') ? 'active' : '' }}"><i class="fa-solid fa-hand-holding-hand" aria-hidden="true"></i><span>Custodie</span></a>
                <a href="{{ route('notifications.index') }}" class="{{ request()->routeIs('notifications.*') ? 'active' : '' }}"><i class="fa-solid fa-bell" aria-hidden="true"></i><span>Notificări</span></a>
            </nav>
        @endif

        @if($impersonationAvailable)
            @include('components.impersonation-selector')
        @endif
    @endauth

    <main class="flex-shrink-0 py-4">
        @if(session('status'))
            <div class="container app-flash-container">
                <div class="alert alert-success alert-dismissible fade show app-flash-message" role="status" data-flash-message data-flash-timeout="4500">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Închide mesajul"></button>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="container">
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Verifica datele introduse.</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-auto py-2 text-center text-white culoare1">
        <p class="mb-1">© {{ date('Y') }} {{ config('app.name') }}</p>
        <span class="small">
            Aplicatie web dezvoltata de
            <a href="https://validsoftware.ro/" class="text-white" target="_blank" rel="noopener">validsoftware.ro</a>
        </span>
    </footer>
    @stack('scripts')
</body>
</html>
