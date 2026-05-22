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

    public function runs(Request $request): Response
    {
        $hoursFilter = $request->filled('hours') ? (int) $request->hours : null;
        $formFilter  = $request->filled('form_id') ? $request->form_id : null;

        $runsQuery = AppLog::whereNotNull('webhook_log_id')
            ->selectRaw('
                webhook_log_id,
                MAX(form_id)     AS form_id,
                MIN(logged_at)   AS started_at,
                MAX(logged_at)   AS finished_at,
                COUNT(*)         AS total_count,
                SUM(level = "error")   AS error_count,
                SUM(level = "warning") AS warning_count,
                SUM(level = "info")    AS info_count
            ')
            ->groupBy('webhook_log_id')
            ->orderByDesc('finished_at')
            ->when($formFilter,  fn ($q) => $q->where('form_id', $formFilter))
            ->when($hoursFilter, fn ($q) => $q->where('logged_at', '>=', now()->subHours($hoursFilter)));

        $runs = $runsQuery->paginate(20)->withQueryString();

        $webhookIds = collect($runs->items())->pluck('webhook_log_id')->filter()->values();

        $entriesByRun = AppLog::whereIn('webhook_log_id', $webhookIds)
            ->orderBy('logged_at', 'asc')
            ->get(['id', 'webhook_log_id', 'level', 'message', 'logged_at', 'context'])
            ->groupBy('webhook_log_id');

        return Inertia::render('Logs/Runs', [
            'runs'           => $runs,
            'entriesByRun'   => $entriesByRun,
            'filters'        => $this->filters(),
            'currentFilters' => $request->only(['form_id', 'hours']),
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
