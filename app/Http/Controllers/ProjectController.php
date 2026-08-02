<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Project;
use App\Services\LocationAccessService;
use App\Services\OperationalAlertSyncService;
use App\Services\ProjectAccessService;
use App\Services\ProjectMaterialPlanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectAccessService $access,
        private readonly ProjectMaterialPlanService $plans,
        private readonly LocationAccessService $locationAccess,
        private readonly OperationalAlertSyncService $alerts,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);
        $user = $request->user();
        $query = $this->access->visibleProjects($user)
            ->with(['location', 'creator', 'materialPlans.catalogItem'])
            ->withCount([
                'materialPlans',
                'transfers as active_transfers_count' => fn (Builder $transfers) => $transfers
                    ->where('purpose', 'transfer')
                    ->where('status', '!=', 'cancelled'),
            ])
            ->when($request->search, fn (Builder $projects, string $search) => $projects
                ->where(function (Builder $matching) use ($search): void {
                    $matching->whereRaw('UPPER(code) LIKE ?', ['%'.Str::upper($search).'%'])
                        ->orWhere('name', 'like', "%{$search}%");
                }))
            ->when($request->status, fn (Builder $projects, string $status) => $projects->where('status', $status))
            ->when($request->integer('location_id'), fn (Builder $projects, int $locationId) => $projects
                ->where('location_id', $locationId))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'draft' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END")
            ->latest('id');

        $projects = $query->paginate(20)->withQueryString();
        $progress = $this->plans->progressForProjects($projects->getCollection());

        return view('projects.index', [
            'projects' => $projects,
            'progressByProject' => $progress,
            'locations' => $this->locationAccess->visibleLocations($user)
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'totalProjects' => $this->access->visibleProjects($user)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);
        $data = $this->validatedData($request);
        $project = DB::transaction(function () use ($data, $request): Project {
            $project = Project::create([
                ...collect($data)->except('lines')->all(),
                'created_by' => $request->user()->id,
            ]);
            $this->replacePlans($project, $data['lines']);

            return $project;
        });
        $this->alerts->sync(force: true);

        return redirect()->route('projects.show', $project)
            ->with('status', 'Proiectul și planul de materiale au fost create.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['location.activeManagers', 'creator', 'materialPlans.catalogItem']);
        $transfers = $project->transfers()
            ->with(['sourceLocation', 'destinationLocation', 'requester', 'lines.catalogItem'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('projects.show', [
            'project' => $project,
            'progress' => $this->plans->progress($project),
            'transfers' => $transfers,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);
        $project->load('materialPlans.catalogItem');

        return view('projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $data = $this->validatedData($request, $project);
        DB::transaction(function () use ($project, $data): void {
            $project->update(collect($data)->except('lines')->all());
            $this->replacePlans($project, $data['lines']);
        });
        $this->alerts->sync(force: true);

        return redirect()->route('projects.show', $project)
            ->with('status', 'Proiectul și planul de materiale au fost actualizate.');
    }

    private function formData(?Project $project = null): array
    {
        return [
            'project' => $project,
            'locations' => $this->locationAccess->visibleLocations(request()->user())
                ->orderBy('type')
                ->orderBy('name')
                ->get(),
            'materials' => CatalogItem::query()
                ->where('active', true)
                ->where('tracking_type', 'quantity')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validatedData(Request $request, ?Project $project = null): array
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('projects', 'code')->ignore($project),
            ],
            'name' => ['required', 'string', 'max:255'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'status' => ['required', Rule::in(array_keys(Project::STATUS_LABELS))],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.catalog_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('tracking_type', 'quantity')),
            ],
            'lines.*.planned_quantity' => ['required', 'numeric', 'min:0.001'],
        ]);
        abort_unless($this->locationAccess->canView($request->user(), (int) $data['location_id']), 403);

        return $data;
    }

    private function replacePlans(Project $project, array $lines): void
    {
        $project->materialPlans()->delete();
        $materials = CatalogItem::query()
            ->whereIn('id', collect($lines)->pluck('catalog_item_id'))
            ->get()
            ->keyBy('id');

        foreach ($lines as $line) {
            $material = $materials->get((int) $line['catalog_item_id']);
            $project->materialPlans()->create([
                'catalog_item_id' => $material->id,
                'planned_quantity' => $line['planned_quantity'],
                'unit' => $material->unit,
            ]);
        }
    }
}
