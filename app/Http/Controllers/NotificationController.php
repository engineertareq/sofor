<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        // Only ever into Sofor itself, so a notification can never become an open redirect.
        $url = (string) ($notification->data['url'] ?? '');
        $url = str_starts_with($url, url('/').'/') || str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $url
            : route('dashboard');

        if ($request->wantsJson()) {
            return response()->json(['redirect' => $url]);
        }

        return redirect($url);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['unread' => 0]);
        }

        return back()->with('status', __('All notifications marked as read.'));
    }
}
