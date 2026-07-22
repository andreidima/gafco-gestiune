<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('view', $task);
        $data = $request->validate(['body' => ['required', 'string']]);
        $task->comments()->create([
            'user_id' => $request->user()->id,
            'type' => 'observation',
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Observatia a fost adaugata.');
    }
}
