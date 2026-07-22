@props([
    'title',
    'description' => null,
    'count' => null,
    'filteredCount' => null,
    'icon' => 'fa-table-list',
    'createRoute' => null,
    'createLabel' => 'Adauga',
])

<div class="resource-page-header">
    <div class="resource-page-heading">
        <span class="resource-page-icon"><i class="fa-solid {{ $icon }}"></i></span>
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h1>{{ $title }}</h1>
                @if($count !== null)
                    <span class="resource-count" @if($filteredCount !== null && (int) $filteredCount !== (int) $count) title="Rezultate filtrate din total" @endif>
                        @if($filteredCount !== null && (int) $filteredCount !== (int) $count)
                            {{ number_format((int) $filteredCount) }} <span>din {{ number_format((int) $count) }}</span>
                        @else
                            {{ number_format((int) $count) }}
                        @endif
                    </span>
                @endif
            </div>
            @if($description)<p>{{ $description }}</p>@endif
        </div>
    </div>
    <div class="resource-page-actions">
        {{ $actions ?? '' }}
        @if($createRoute)
            <a href="{{ $createRoute }}" class="btn btn-success btn-sm resource-create-button">
                <i class="fa-solid fa-plus me-1"></i>{{ $createLabel }}
            </a>
        @endif
    </div>
</div>
