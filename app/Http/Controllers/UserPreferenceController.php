<?php

namespace App\Http\Controllers;

use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPreferenceController extends Controller
{
    public function updateInventory(Request $request): JsonResponse
    {
        $data = $request->validate([
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

        UserPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'inventory.index'],
            ['value' => $data]
        );

        return response()->json(['saved' => true]);
    }
}
