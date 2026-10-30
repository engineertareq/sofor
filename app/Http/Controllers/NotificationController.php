<?php

namespace App\Http\Controllers;

use App\Support\Bangla;
use Illuminate\Http\JsonResponse;
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

    /**
     * The alerts panel (resources/js/alerts.js): one page of alerts as rendered
     * rows, the unread count for the badges, and the next page's address.
     * Cursor paging, so alerts that arrive while the panel is open never push
     * a row onto the next page twice.
     */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        $page = ($request->boolean('unread') ? $user->unreadNotifications() : $user->notifications())
            ->reorder()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(15)
            ->withQueryString();

        $html = collect($page->items())
            ->map(fn ($notification) => view('notifications.partials.item', ['notification' => $notification])->render())
            ->implode('');

        return response()->json([
            // LocalizeDigits only rewrites whole pages, so the rows get their Bangla digits here.
            'html' => Bangla::active() ? Bangla::html($html) : $html,
            'unread' => $user->unreadNotifications()->count(),
            'next' => $page->nextPageUrl(),
        ])->header('Cache-Control', 'no-store, private');
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
            return response()->json([
                'redirect' => $url,
                'unread' => $request->user()->unreadNotifications()->count(),
            ]);
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
