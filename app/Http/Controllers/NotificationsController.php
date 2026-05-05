<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = WebhookLog::query()->orderBy('created_at', 'desc');

        if ($request->filled('filter')) {
            match ($request->filter) {
                'unread' => $query->whereNull('read_at'),
                'read'   => $query->whereNotNull('read_at'),
                default  => null,
            };
        }

        $notifications = $query->paginate(20)->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount'   => WebhookLog::whereNull('read_at')->count(),
            'currentFilter' => $request->get('filter', 'all'),
        ]);
    }

    public function markRead(WebhookLog $log): JsonResponse
    {
        $log->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(): JsonResponse
    {
        WebhookLog::whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => WebhookLog::whereNull('read_at')->count()]);
    }
}
