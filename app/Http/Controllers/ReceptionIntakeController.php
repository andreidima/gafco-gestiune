<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\ReceptionDocument;
use App\Models\ReceptionIntake;
use App\Services\ReceptionAccessService;
use App\Services\ReceptionDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReceptionIntakeController extends Controller
{
    public function __construct(
        private readonly ReceptionAccessService $access,
        private readonly ReceptionDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasAbility('reception-intakes.view'), 403);

        $query = $this->access->visibleIntakes($user)
            ->with(['location', 'submitter', 'processor', 'reception'])
            ->withCount('documents')
            ->when($request->search, fn (Builder $builder, string $search) => $builder
                ->where(function (Builder $matching) use ($search): void {
                    $matching->where('number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                }))
            ->when($request->status, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($request->location_id, fn (Builder $builder, string $locationId) => $builder->where('location_id', $locationId))
            ->latest();

        return view('reception-intakes.index', [
            'intakes' => $query->paginate(20)->withQueryString(),
            'locations' => $this->filterLocations($user)->get(),
            'openCount' => (clone $this->access->visibleIntakes($user))->where('status', 'created')->count(),
            'canUpload' => $user->hasAbility('reception-documents.upload'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasAbility('reception-documents.upload'), 403);

        return view('reception-intakes.create', [
            'locations' => $this->uploadLocations($request)->get(),
            'documentTypes' => ReceptionDocument::TYPE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasAbility('reception-documents.upload'), 403);
        $locationIds = $this->uploadLocations($request)->pluck('id');
        $data = $this->validatePayload($request, $locationIds->all());
        $storedDocuments = collect();

        try {
            $intake = DB::transaction(function () use ($data, $user, &$storedDocuments): ReceptionIntake {
                $intake = ReceptionIntake::create([
                    'number' => 'DR-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                    'location_id' => $data['location_id'],
                    'submitted_by' => $user->id,
                    'status' => 'created',
                    'notes' => $data['notes'] ?? null,
                ]);
                $storedDocuments = $this->documents->store(
                    $this->normalizedAttachments($data['attachments']),
                    $user,
                    intakeId: $intake->id,
                );
                activity()
                    ->performedOn($intake)
                    ->causedBy($user)
                    ->withProperties(['document_count' => $storedDocuments->count()])
                    ->log('Documente de recepție încărcate');

                return $intake;
            });
        } catch (\Throwable $exception) {
            $this->documents->remove($storedDocuments);
            throw $exception;
        }

        return redirect()
            ->route('reception-intakes.show', $intake)
            ->with('status', 'Documentele au fost trimise pentru procesare.');
    }

    public function show(Request $request, ReceptionIntake $receptionIntake): View
    {
        abort_unless($this->access->canViewIntake($request->user(), $receptionIntake), 403);
        $receptionIntake->load(['location', 'submitter', 'processor', 'reception', 'documents.uploader']);

        return view('reception-intakes.show', [
            'intake' => $receptionIntake,
            'canProcess' => $this->access->canProcessIntake($request->user(), $receptionIntake),
            'documentTypes' => ReceptionDocument::TYPE_LABELS,
        ]);
    }

    public function cancel(Request $request, ReceptionIntake $receptionIntake): RedirectResponse
    {
        abort_unless($this->access->canCancelIntake($request->user(), $receptionIntake), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $receptionIntake, $data): void {
            $lockedIntake = ReceptionIntake::query()->lockForUpdate()->findOrFail($receptionIntake->id);
            abort_unless($this->access->canCancelIntake($request->user(), $lockedIntake), 409);

            $lockedIntake->update([
                'status' => 'closed',
                'closure_type' => 'cancelled',
                'processed_by' => $request->user()->id,
                'closed_at' => now(),
                'notes' => trim((string) $lockedIntake->notes."\nAnulare: ".$data['reason']),
            ]);
            activity()
                ->performedOn($lockedIntake)
                ->causedBy($request->user())
                ->withProperties(['reason' => $data['reason']])
                ->log('Documente de recepție închise fără recepție');
        });

        return redirect()
            ->route('reception-intakes.show', $receptionIntake)
            ->with('status', 'Înregistrarea a fost închisă și păstrată în istoric.');
    }

    private function validatePayload(Request $request, array $locationIds): array
    {
        $validator = Validator::make($request->all(), [
            'location_id' => ['required', 'integer', Rule::in($locationIds)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'attachments' => ['required', 'array', 'min:1', 'max:10'],
            'attachments.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
            'attachments.*.type' => ['required', Rule::in(array_keys(ReceptionDocument::TYPE_LABELS))],
            'attachments.*.custom_label' => ['nullable', 'string', 'max:160'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            foreach ((array) $request->input('attachments', []) as $index => $attachment) {
                if (($attachment['type'] ?? null) === 'custom' && blank($attachment['custom_label'] ?? null)) {
                    $validator->errors()->add("attachments.{$index}.custom_label", 'Completează denumirea documentului personalizat.');
                }
            }
        });

        return $validator->validate();
    }

    private function normalizedAttachments(array $attachments): array
    {
        return array_values($attachments);
    }

    private function filterLocations($user): Builder
    {
        $scope = $user->abilityScope('reception-intakes.view');
        if ($scope === 'global') {
            return Location::query()->where('active', true)->orderBy('type')->orderBy('name');
        }

        if (in_array($scope, ['assigned_locations', 'visible_records'], true)) {
            return Location::query()
                ->where('active', true)
                ->whereIn('id', $user->activeManagedLocations()
                    ->where('locations.active', true)
                    ->pluck('locations.id'))
                ->orderBy('type')
                ->orderBy('name');
        }

        return Location::query()
            ->whereIn('id', $this->access->visibleIntakes($user)->select('location_id'))
            ->orderBy('type')
            ->orderBy('name');
    }

    private function uploadLocations(Request $request): Builder
    {
        $user = $request->user();

        $scope = $user->abilityScope('reception-documents.upload');

        return Location::query()
            ->where('active', true)
            ->when(
                ! in_array($scope, ['global', 'selected_location'], true),
                fn (Builder $query) => $query->whereIn(
                    'id',
                    $user->activeManagedLocations()->where('locations.active', true)->pluck('locations.id'),
                ),
            )
            ->orderBy('type')
            ->orderBy('name');
    }
}
