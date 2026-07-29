<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Project;
use App\Models\TransferLine;
use Illuminate\Support\Collection;

class ProjectMaterialPlanService
{
    /**
     * @return Collection<int, array{
     *     catalog_item: CatalogItem,
     *     planned_quantity: float,
     *     committed_quantity: float,
     *     remaining_quantity: float,
     *     overrun_quantity: float,
     *     progress_percent: float|null,
     *     visual_percent: float,
     *     unit: string,
     *     is_planned: bool,
     *     has_overrun: bool
     * }>
     */
    public function progress(Project $project, ?int $ignoreTransferId = null): Collection
    {
        $progress = $this->progressForProjects(collect([$project]), $ignoreTransferId);

        return $progress->get($project->id, collect());
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function progressForProjects(Collection $projects, ?int $ignoreTransferId = null): Collection
    {
        $projects = $projects->filter()->unique('id')->values();
        if ($projects->isEmpty()) {
            return collect();
        }

        $projects->each->loadMissing('materialPlans.catalogItem');
        $committedRows = TransferLine::query()
            ->join('transfers', 'transfers.id', '=', 'transfer_lines.transfer_id')
            ->selectRaw('transfers.project_id, transfer_lines.catalog_item_id, SUM(transfer_lines.quantity) as committed_quantity')
            ->whereIn('transfers.project_id', $projects->pluck('id'))
            ->where('transfers.purpose', 'transfer')
            ->where('transfers.status', '!=', 'cancelled')
            ->whereNull('transfer_lines.tracked_asset_id')
            ->when($ignoreTransferId, fn ($query) => $query->where('transfers.id', '!=', $ignoreTransferId))
            ->groupBy('transfers.project_id', 'transfer_lines.catalog_item_id')
            ->get()
            ->groupBy(fn ($row) => (int) $row->project_id);

        $unplannedItemIds = $committedRows
            ->flatten(1)
            ->pluck('catalog_item_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $catalogItems = CatalogItem::query()
            ->whereIn('id', $unplannedItemIds)
            ->get()
            ->keyBy('id');

        return $projects->mapWithKeys(function (Project $project) use ($committedRows, $catalogItems): array {
            $plans = $project->materialPlans->keyBy(fn ($plan) => (int) $plan->catalog_item_id);
            $committed = $committedRows->get($project->id, collect())
                ->keyBy(fn ($row) => (int) $row->catalog_item_id);
            $itemIds = $plans->keys()->merge($committed->keys())->unique()->sort()->values();

            $rows = $itemIds->map(function (int $catalogItemId) use ($plans, $committed, $catalogItems): array {
                $plan = $plans->get($catalogItemId);
                $catalogItem = $plan?->catalogItem ?? $catalogItems->get($catalogItemId);
                $plannedQuantity = round((float) ($plan?->planned_quantity ?? 0), 3);
                $committedQuantity = round((float) ($committed->get($catalogItemId)?->committed_quantity ?? 0), 3);
                $remainingQuantity = max(0, round($plannedQuantity - $committedQuantity, 3));
                $overrunQuantity = max(0, round($committedQuantity - $plannedQuantity, 3));
                $progressPercent = $plannedQuantity > 0
                    ? round(($committedQuantity / $plannedQuantity) * 100, 1)
                    : null;

                return [
                    'catalog_item' => $catalogItem,
                    'planned_quantity' => $plannedQuantity,
                    'committed_quantity' => $committedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'overrun_quantity' => $overrunQuantity,
                    'progress_percent' => $progressPercent,
                    'visual_percent' => min(100, $progressPercent ?? ($committedQuantity > 0 ? 100 : 0)),
                    'unit' => $plan?->unit ?? $catalogItem?->unit ?? 'buc',
                    'is_planned' => (bool) $plan,
                    'has_overrun' => $overrunQuantity > 0.0005,
                ];
            })->filter(fn (array $row) => $row['catalog_item'] !== null)->values();

            return [$project->id => $rows];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function overruns(Project $project): Collection
    {
        return $this->progress($project)
            ->where('has_overrun', true)
            ->values();
    }
}
