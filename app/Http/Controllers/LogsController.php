<?php

namespace App\Http\Controllers;

use App\Models\AppLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AppLog::query()->orderBy('logged_at', 'desc');

        if ($request->filled('level')) {
            $query->level($request->level);
        }

        if ($request->filled('channel')) {
            $query->channel($request->channel);
        }

        if ($request->filled('form_id')) {
            $query->formId($request->form_id);
        }

        if ($request->filled('search')) {
            $query->where('message', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('hours')) {
            $query->recent((int) $request->hours);
        }

        $logs = $query->paginate(50)->withQueryString();

        $stats = [
            'total'      => AppLog::count(),
            'errors'     => AppLog::level('error')->count(),
            'warnings'   => AppLog::level('warning')->count(),
            'info'       => AppLog::level('info')->count(),
            'recent_24h' => AppLog::recent(24)->count(),
        ];

        $levels   = AppLog::distinct('level')->pluck('level')->sort()->values();
        $channels = AppLog::distinct('channel')->pluck('channel')->sort()->values();
        $formIds  = AppLog::whereNotNull('form_id')->distinct('form_id')->pluck('form_id')->sort()->values();

        return Inertia::render('Logs/Index', [
            'logs'           => $logs,
            'stats'          => $stats,
            'filters'        => [
                'levels'   => $levels,
                'channels' => $channels,
                'form_ids' => $formIds,
            ],
            'currentFilters' => $request->only(['level', 'channel', 'form_id', 'search', 'hours']),
        ]);
    }

    public function show(AppLog $log): Response
    {
        return Inertia::render('Logs/Show', [
            'log' => $log,
        ]);
    }

    public function clear(Request $request)
    {
        $query = AppLog::query();

        if ($request->filled('level')) {
            $query->level($request->level);
        }

        if ($request->filled('older_than_days')) {
            $query->where('logged_at', '<', now()->subDays((int) $request->older_than_days));
        } else {
            $query->where('logged_at', '<', now()->subDays(30));
        }

        $deleted = $query->delete();

        return back()->with('success', "Cleared {$deleted} log entries.");
    }
}
