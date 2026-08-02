<?php

use App\Http\Controllers\AlertRuleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ConsumptionReportController;
use App\Http\Controllers\CustodyTransferController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverRequestController;
use App\Http\Controllers\FieldModeController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LegacyRouteRedirectController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NegotiatedOrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperationalAlertController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QrScanController;
use App\Http\Controllers\ReceptionDocumentController;
use App\Http\Controllers\ReceptionIntakeController;
use App\Http\Controllers\ReleaseNoteController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierReceptionController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskWhatsAppController;
use App\Http\Controllers\TrackedAssetController;
use App\Http\Controllers\TransferApprovalController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Middleware\AuditImpersonatedRequest;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RejectImpersonatedRequest;
use App\Http\Middleware\RememberFilterPreferences;
use App\Http\Middleware\SyncOperationalAlerts;
use App\Http\Middleware\ValidateImpersonation;
use Illuminate\Support\Facades\Route;

Route::resourceVerbs([
    'create' => 'adauga',
    'edit' => 'modifica',
]);

Route::middleware('guest')->group(function () {
    Route::get('/autentificare', [LoginController::class, 'create'])->name('login');
    Route::get('/login', fn () => redirect()->route('login', request()->query()));
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/deconectare', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('landing');

Route::middleware([
    'auth',
    ValidateImpersonation::class,
    EnsureUserIsActive::class,
    AuditImpersonatedRequest::class,
    SyncOperationalAlerts::class,
    RememberFilterPreferences::class,
])->group(function () {
    Route::get('/acasa', DashboardController::class)->name('dashboard');
    Route::get('/ajutor', [HelpCenterController::class, 'index'])->name('help.index');
    Route::get('/ajutor/{helpArticle}', [HelpCenterController::class, 'show'])->name('help.show');
    Route::get('/noutati', [ReleaseNoteController::class, 'index'])->name('release-notes.index');
    Route::get('/noutati/{releaseNote}', [ReleaseNoteController::class, 'show'])->name('release-notes.show');

    Route::get('/impersonare/utilizatori', [ImpersonationController::class, 'users'])
        ->name('impersonation.users');
    Route::post('/impersonare/oprire', [ImpersonationController::class, 'stop'])
        ->name('impersonation.stop');
    Route::post('/impersonare/utilizatori/{user}', [ImpersonationController::class, 'take'])
        ->middleware('throttle:30,1')
        ->name('impersonation.take');

    Route::resource('locatii', LocationController::class)->only(['create', 'edit'])
        ->names('locations')
        ->parameters(['locatii' => 'location'])
        ->middleware('role:super-admin|admin|dispecer');
    Route::resource('locatii', LocationController::class)->only(['index'])
        ->names('locations')
        ->parameters(['locatii' => 'location'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza');
    Route::resource('locations', LocationController::class)->only(['store', 'update'])
        ->middleware('role:super-admin|admin|dispecer');

    Route::resource('nomenclator', CatalogItemController::class)->only(['create', 'edit'])
        ->names('catalog-items')
        ->parameters(['nomenclator' => 'catalog_item'])
        ->middleware('role:super-admin|admin|dispecer|gestionar-baza');
    Route::resource('nomenclator', CatalogItemController::class)->only(['index'])
        ->names('catalog-items')
        ->parameters(['nomenclator' => 'catalog_item'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza');
    Route::resource('catalog-items', CatalogItemController::class)->only(['store', 'update'])
        ->middleware('role:super-admin|admin|dispecer|gestionar-baza');

    Route::resource('furnizori', SupplierController::class)->only(['index'])
        ->names('suppliers')
        ->parameters(['furnizori' => 'supplier'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');
    Route::resource('furnizori', SupplierController::class)->only(['create', 'edit'])
        ->names('suppliers')
        ->parameters(['furnizori' => 'supplier'])
        ->middleware('permission:suppliers.manage');
    Route::resource('suppliers', SupplierController::class)->only(['store', 'update'])
        ->middleware('permission:suppliers.manage');
    Route::post('suppliers/{supplier}/deactivate', [SupplierController::class, 'deactivate'])
        ->middleware('permission:suppliers.manage')
        ->name('suppliers.deactivate');
    Route::post('suppliers/{supplier}/activate', [SupplierController::class, 'activate'])
        ->middleware('permission:suppliers.manage')
        ->name('suppliers.activate');

    Route::get('inventar', [InventoryController::class, 'index'])->name('inventory.index')
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');
    Route::get('inventar/{catalogItem}', [InventoryController::class, 'show'])->name('inventory.show')
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');
    Route::put('preferences/inventory', [UserPreferenceController::class, 'updateInventory'])->name('preferences.inventory.update')
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');

    Route::resource('echipamente', TrackedAssetController::class)->only(['create', 'edit'])
        ->names('tracked-assets')
        ->parameters(['echipamente' => 'tracked_asset'])
        ->middleware('role:super-admin|admin|dispecer');
    Route::resource('echipamente', TrackedAssetController::class)->only(['index'])
        ->names('tracked-assets')
        ->parameters(['echipamente' => 'tracked_asset'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza');
    Route::resource('echipamente', TrackedAssetController::class)->only(['show'])
        ->names('tracked-assets')
        ->parameters(['echipamente' => 'tracked_asset'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|sofer|muncitor|contabil');
    Route::resource('tracked-assets', TrackedAssetController::class)->only(['store', 'update'])
        ->middleware('role:super-admin|admin|dispecer');

    Route::resource('proiecte', ProjectController::class)->only(['index', 'create', 'show', 'edit'])
        ->names('projects')
        ->parameters(['proiecte' => 'project'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza');
    Route::resource('projects', ProjectController::class)->only(['store', 'update'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza');

    Route::get('transferuri/optiuni-sursa', [TransferController::class, 'sourceOptions'])->name('transfers.source-options');
    Route::resource('transferuri', TransferController::class)->only(['index', 'create', 'show', 'edit'])
        ->names('transfers')
        ->parameters(['transferuri' => 'transfer']);
    Route::resource('transfers', TransferController::class)->only(['store', 'update']);
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    Route::post('transfers/{transfer}/archive', [TransferController::class, 'archive'])->name('transfers.archive');
    Route::put('transfer-approvals/{approval}', [TransferApprovalController::class, 'update'])->name('transfer-approvals.update');

    Route::get('cereri-sofer', [DriverRequestController::class, 'index'])->name('driver-requests.index');
    Route::get('sarcini/situatie-soferi', [TaskController::class, 'dispatch'])->name('tasks.dispatch');
    Route::resource('sarcini', TaskController::class)->only(['index', 'create', 'show', 'edit'])
        ->names('tasks')
        ->parameters(['sarcini' => 'task']);
    Route::resource('tasks', TaskController::class)->only(['store', 'update']);
    Route::post('tasks/{task}/transition', [TaskController::class, 'transition'])->name('tasks.transition');
    Route::post('tasks/{task}/assignments', [TaskAssignmentController::class, 'store'])->name('tasks.assignments.store');
    Route::post('task-assignments/{assignment}/respond', [TaskAssignmentController::class, 'respond'])->name('task-assignments.respond');
    Route::post('task-assignments/{assignment}/estimate', [TaskAssignmentController::class, 'estimate'])->name('task-assignments.estimate');
    Route::post('task-assignments/{assignment}/request-reassignment', [TaskAssignmentController::class, 'requestReassignment'])->name('task-assignments.request-reassignment');
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::get('sarcini/{task}/whatsapp', TaskWhatsAppController::class)->name('tasks.whatsapp');

    Route::get('notificari', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('alerte', [OperationalAlertController::class, 'index'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil')
        ->name('alerts.index');
    Route::get('setari/alerte', [AlertRuleController::class, 'index'])
        ->middleware('role:super-admin|admin')
        ->name('alert-rules.index');
    Route::post('settings/alerts', [AlertRuleController::class, 'store'])
        ->middleware(['role:super-admin|admin', RejectImpersonatedRequest::class])
        ->name('alert-rules.store');
    Route::delete('settings/alerts/{alertRule}', [AlertRuleController::class, 'destroy'])
        ->middleware(['role:super-admin|admin', RejectImpersonatedRequest::class])
        ->name('alert-rules.destroy');

    Route::get('documente-de-procesat/trimite', [ReceptionIntakeController::class, 'create'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|muncitor')
        ->name('reception-intakes.create');
    Route::resource('documente-de-procesat', ReceptionIntakeController::class)->only(['index', 'show'])
        ->names('reception-intakes')
        ->parameters(['documente-de-procesat' => 'reception_intake'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|muncitor');
    Route::post('reception-intakes', [ReceptionIntakeController::class, 'store'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|muncitor')
        ->name('reception-intakes.store');
    Route::post('reception-intakes/{receptionIntake}/cancel', [ReceptionIntakeController::class, 'cancel'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza')
        ->name('reception-intakes.cancel');
    Route::get('documente-receptie/{receptionDocument}/descarca', ReceptionDocumentController::class)
        ->name('reception-documents.download');
    Route::get('documente-receptie/{receptionDocument}/previzualizare', [ReceptionDocumentController::class, 'preview'])
        ->name('reception-documents.preview');

    Route::resource('receptii', SupplierReceptionController::class)->only(['create'])
        ->names('supplier-receptions')
        ->parameters(['receptii' => 'supplier_reception'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('receptii', SupplierReceptionController::class)->only(['index', 'show'])
        ->names('supplier-receptions')
        ->parameters(['receptii' => 'supplier_reception'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');
    Route::resource('receptii', SupplierReceptionController::class)->only(['edit'])
        ->names('supplier-receptions')
        ->parameters(['receptii' => 'supplier_reception']);
    Route::resource('supplier-receptions', SupplierReceptionController::class)->only(['store'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('supplier-receptions', SupplierReceptionController::class)->only(['update']);

    Route::resource('comenzi-negociate', NegotiatedOrderController::class)->only(['index', 'create', 'show', 'edit'])
        ->names('negotiated-orders')
        ->parameters(['comenzi-negociate' => 'negotiatedOrder'])
        ->middleware('role:super-admin|admin');
    Route::resource('negotiated-orders', NegotiatedOrderController::class)->only(['store', 'update'])
        ->parameters(['negotiated-orders' => 'negotiatedOrder'])
        ->middleware('role:super-admin|admin');
    Route::post('negotiated-orders/{negotiatedOrder}/cancel', [NegotiatedOrderController::class, 'cancel'])
        ->middleware('role:super-admin|admin')
        ->name('negotiated-orders.cancel');

    Route::get('consumuri/propunere-alocare', [ConsumptionReportController::class, 'allocationProposal'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza')
        ->name('consumption-reports.allocation-proposal');
    Route::get('consumuri/optiuni-stoc', [ConsumptionReportController::class, 'stockOptions'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza')
        ->name('consumption-reports.stock-options');
    Route::resource('consumuri', ConsumptionReportController::class)->only(['index'])
        ->names('consumption-reports')
        ->parameters(['consumuri' => 'consumption_report'])
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');
    Route::resource('consumuri', ConsumptionReportController::class)->only(['create'])
        ->names('consumption-reports')
        ->parameters(['consumuri' => 'consumption_report'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('consumuri', ConsumptionReportController::class)->only(['edit'])
        ->names('consumption-reports')
        ->parameters(['consumuri' => 'consumption_report'])
        ->middleware(['role:super-admin|admin', RejectImpersonatedRequest::class]);
    Route::resource('consumption-reports', ConsumptionReportController::class)->only(['store'])
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza');
    Route::resource('consumption-reports', ConsumptionReportController::class)->only(['update'])
        ->middleware(['role:super-admin|admin', RejectImpersonatedRequest::class]);

    Route::resource('custody-transfers', CustodyTransferController::class)->only(['store', 'update']);
    Route::get('retururi', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('teren/sofer', [FieldModeController::class, 'driver'])->name('field.driver');
    Route::get('teren/sef-santier', [FieldModeController::class, 'siteManager'])->name('field.site-manager');
    Route::get('teren/muncitor', [FieldModeController::class, 'worker'])->name('field.worker')
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|sofer|muncitor');
    Route::get('scanare-qr', [QrScanController::class, 'index'])->name('qr-scan.index')
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|sofer|muncitor');
    Route::post('qr-scan', [QrScanController::class, 'lookup'])->name('qr-scan.lookup')
        ->middleware('role:super-admin|admin|dispecer|sef-santier|gestionar-baza|sofer|muncitor');
    Route::get('rapoarte', [ReportController::class, 'index'])->name('reports.index')
        ->middleware('role:super-admin|admin|dispecer|manager|sef-santier|gestionar-baza|contabil');

    Route::resource('utilizatori', UserController::class)->only(['index', 'create', 'edit'])
        ->names('users')
        ->parameters(['utilizatori' => 'user'])
        ->middleware(['role:admin|super-admin', RejectImpersonatedRequest::class]);
    Route::resource('users', UserController::class)->only(['store', 'update'])
        ->middleware(['role:admin|super-admin', RejectImpersonatedRequest::class]);

    Route::get('{legacyPath}', LegacyRouteRedirectController::class)
        ->where('legacyPath', '(?:dashboard|locations(?:/.*)?|catalog-items(?:/.*)?|suppliers(?:/.*)?|inventory(?:/.*)?|tracked-assets(?:/.*)?|projects(?:/.*)?|transfers(?:/.*)?|driver-requests|tasks(?:/.*)?|notifications|alerts|settings/alerts|reception-intakes(?:/.*)?|reception-documents(?:/.*)?|supplier-receptions(?:/.*)?|negotiated-orders(?:/.*)?|consumption-reports(?:/.*)?|returns|field(?:/.*)?|qr-scan|reports|users(?:/.*)?)');
});
