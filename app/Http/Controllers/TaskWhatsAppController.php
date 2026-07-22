<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TaskWhatsAppController extends Controller
{
    public function __invoke(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('assign', $task);
        $data = $request->validate(['user_id' => ['required', 'exists:users,id']]);
        $recipient = User::whereKey($data['user_id'])->whereNotNull('phone')->firstOrFail();
        $phone = preg_replace('/\D+/', '', $recipient->phone);
        if (str_starts_with($phone, '0')) {
            $phone = '40'.substr($phone, 1);
        }
        $message = implode("\n", array_filter([
            'GAFCO - '.$task->number,
            $task->title,
            $task->manager_deadline ? 'Deadline: '.$task->manager_deadline->format('d.m.Y H:i') : null,
            $task->sourceLocation?->name && $task->destinationLocation?->name
                ? $task->sourceLocation->name.' -> '.$task->destinationLocation->name
                : null,
            'Detalii: '.route('tasks.show', $task),
        ]));

        return redirect()->away('https://wa.me/'.$phone.'?text='.rawurlencode($message));
    }
}
