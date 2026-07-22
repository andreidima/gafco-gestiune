<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\TaskWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskAssignmentController extends Controller
{
    public function store(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('assign', $task);
        $data = $request->validate(['driver_id' => ['required', 'exists:users,id']]);
        $driver = User::assignableDrivers()->where('active', true)->whereKey($data['driver_id'])->firstOrFail();
        $workflow->assign($task, $driver, $request->user());

        return back()->with('status', 'Solicitarea a fost trimisa soferului.');
    }

    public function respond(Request $request, TaskAssignment $assignment, TaskWorkflowService $workflow): RedirectResponse
    {
        $assignment->loadMissing('task.currentAssignment');
        $this->authorize('respond', $assignment->task);
        abort_unless($assignment->driver_id === $request->user()->id && $assignment->status === 'pending', 403);
        $data = $request->validate([
            'decision' => ['required', 'in:accepted,rejected'],
            'response_notes' => ['nullable', 'required_if:decision,rejected', 'string'],
        ]);
        $workflow->respond($assignment, $request->user(), $data['decision'], $data['response_notes'] ?? null);

        return back()->with('status', 'Raspunsul tau a fost inregistrat.');
    }

    public function estimate(Request $request, TaskAssignment $assignment, TaskWorkflowService $workflow): RedirectResponse
    {
        $assignment->loadMissing('task.currentAssignment');
        $this->authorize('respond', $assignment->task);
        abort_unless($assignment->driver_id === $request->user()->id && in_array($assignment->status, ['accepted', 'reassignment_requested'], true), 403);
        $data = $request->validate([
            'driver_estimate_at' => ['required', 'date'],
            'driver_estimate_note' => ['required', 'string'],
        ]);
        $workflow->updateEstimate($assignment, $request->user(), $data['driver_estimate_at'], $data['driver_estimate_note']);

        return back()->with('status', 'Estimarea si observatia au fost salvate.');
    }

    public function requestReassignment(Request $request, TaskAssignment $assignment, TaskWorkflowService $workflow): RedirectResponse
    {
        $assignment->loadMissing('task.currentAssignment');
        $this->authorize('respond', $assignment->task);
        abort_unless($assignment->driver_id === $request->user()->id && $assignment->status === 'accepted', 403);
        $data = $request->validate(['notes' => ['required', 'string']]);
        $workflow->requestReassignment($assignment, $request->user(), $data['notes']);

        return back()->with('status', 'Solicitarea de realocare a fost trimisa managerului.');
    }
}
