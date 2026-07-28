<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use App\Services\LocationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    public function updateInventory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filters' => ['required', 'array'],
            'filters.search' => ['nullable', 'string', 'max:255'],
            'filters.location_id' => ['nullable', 'integer'],
            'filters.hide_zero' => ['required', 'boolean'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => [
                'string',
                Rule::in(['locations', 'lots', 'supplier', 'document', 'received_at', 'expiration', 'price']),
            ],
            'density' => ['required', Rule::in(['compact', 'comfortable'])],
        ]);

        if (! $request->user()->canViewCommercialInventory()) {
            $data['columns'] = array_values(array_diff($data['columns'], ['price']));
        }
        $locationId = (int) ($data['filters']['location_id'] ?? 0);
        if ($locationId && ! app(LocationAccessService::class)->canView($request->user(), $locationId)) {
            abort(403);
        }

        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'inventory.index'],
            ['value' => $data]
        );

        return response()->json(['saved' => true]);
    }
}
