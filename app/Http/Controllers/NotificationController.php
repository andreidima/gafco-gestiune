<?php

namespace App\Http\Controllers;

use App\Support\RomanianUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(30),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification, RomanianUrl $romanianUrl): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();
        $url = $item->data['url'] ?? null;
        $isLocalUrl = is_string($url)
            && ((str_starts_with($url, '/') && ! str_starts_with($url, '//'))
                || str_starts_with($url, rtrim(url('/'), '/').'/'));

        return redirect($isLocalUrl ? $romanianUrl->translate($url) : route('dashboard'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificarile au fost marcate ca citite.');
    }
}
