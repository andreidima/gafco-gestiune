<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Location;
use App\Services\AlertRuleResolver;
use App\Services\OperationalAlertSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AlertRuleController extends Controller
{
    public function __construct(
        private readonly AlertRuleResolver $resolver,
        private readonly OperationalAlertSyncService $sync,
    ) {}

    public function index(): View
    {
        return view('alert-rules.index', [
            'rules' => $this->resolver->rules(),
            'definitions' => AlertRuleResolver::TYPES,
            'roles' => collect(AlertRuleResolver::CONFIGURABLE_ROLES)
                ->mapWithKeys(fn (string $role) => [$role => config("roles.labels.{$role}", $role)])
                ->all(),
            'locations' => Location::query()->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'alert_type' => ['required', Rule::in(array_keys(AlertRuleResolver::TYPES))],
            'scope_type' => ['required', Rule::in(['system', 'role', 'location'])],
            'role_name' => [
                Rule::requiredIf($request->input('scope_type') === 'role'),
                'nullable',
                Rule::in(AlertRuleResolver::CONFIGURABLE_ROLES),
            ],
            'location_id' => [
                Rule::requiredIf($request->input('scope_type') === 'location'),
                'nullable',
                'integer',
                'exists:locations,id',
            ],
            'enabled' => ['required', 'boolean'],
            'threshold_days' => ['required', 'integer', 'between:0,365'],
        ]);

        $scopeKey = match ($data['scope_type']) {
            'system' => 'system',
            'role' => 'role:'.$data['role_name'],
            'location' => 'location:'.$data['location_id'],
        };
        $rule = AlertRule::query()->updateOrCreate(
            [
                'alert_type' => $data['alert_type'],
                'scope_key' => $scopeKey,
            ],
            [
                'scope_type' => $data['scope_type'],
                'role_name' => $data['scope_type'] === 'role' ? $data['role_name'] : null,
                'location_id' => $data['scope_type'] === 'location' ? $data['location_id'] : null,
                'enabled' => (bool) $data['enabled'],
                'threshold_days' => $data['threshold_days'],
                'changed_by' => $request->user()->id,
            ],
        );

        activity()
            ->performedOn($rule)
            ->causedBy($request->user())
            ->withProperties([
                'alert_type' => $rule->alert_type,
                'scope_key' => $rule->scope_key,
                'enabled' => $rule->enabled,
                'threshold_days' => $rule->threshold_days,
            ])
            ->log('Regulă de alertare actualizată');
        $this->sync->sync(force: true);

        return back()->with('status', 'Regula de alertare a fost salvată.');
    }

    public function destroy(Request $request, AlertRule $alertRule): RedirectResponse
    {
        abort_if($alertRule->scope_type === 'system', 422, 'Regula generală nu poate fi ștearsă.');

        $properties = [
            'alert_type' => $alertRule->alert_type,
            'scope_key' => $alertRule->scope_key,
        ];
        activity()
            ->performedOn($alertRule)
            ->causedBy($request->user())
            ->withProperties($properties)
            ->log('Excepție de alertare eliminată');
        $alertRule->delete();
        $this->sync->sync(force: true);

        return back()->with('status', 'Excepția a fost eliminată; se aplică din nou regula cu prioritate mai mică.');
    }
}
