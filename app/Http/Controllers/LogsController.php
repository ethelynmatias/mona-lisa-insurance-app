<?php

namespace App\Http\Controllers;

use App\Models\AppLog;
use App\QueryBuilders\AppLogQueryBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogsController extends Controller
{
    public function index(Request $request, AppLogQueryBuilder $builder): Response
    {
        $logs = $builder->build()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Logs/Index', [
            'logs'           => $logs,
            'stats'          => $this->stats(),
            'filters'        => $this->filters(),
            'currentFilters' => $request->only(['level', 'channel', 'form_id', 'search', 'hours']),
        ]);
    }

    public function show(AppLog $log): Response
    {
        return Inertia::render('Logs/Show', [
            'log' => $log,
        ]);
    }

    public function clear(Request $request, AppLogQueryBuilder $builder)
    {
        $query = $builder->build();

        $query->when(
            $request->older_than_days,
            fn ($q, $v) => $q->where('logged_at', '<', now()->subDays((int) $v)),
            fn ($q)     => $q->where('logged_at', '<', now()->subDays(30))
        );

        $deleted = $query->delete();

        return back()->with('success', "Cleared {$deleted} log entries.");
    }

    private function stats(): array
    {
        return [
            'total'      => AppLog::count(),
            'errors'     => AppLog::level('error')->count(),
            'warnings'   => AppLog::level('warning')->count(),
            'info'       => AppLog::level('info')->count(),
            'recent_24h' => AppLog::recent(24)->count(),
        ];
    }

    private function filters(): array
    {
        return [
            'levels'   => AppLog::distinct('level')->pluck('level')->sort()->values(),
            'channels' => AppLog::distinct('channel')->pluck('channel')->sort()->values(),
            'form_ids' => AppLog::whereNotNull('form_id')->distinct('form_id')->pluck('form_id')->sort()->values(),
        ];
    }
}
