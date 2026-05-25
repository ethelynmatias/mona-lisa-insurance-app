import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const LEVEL_COLORS = {
    error:   'bg-red-100 text-red-800',
    warning: 'bg-yellow-100 text-yellow-800',
    info:    'bg-blue-100 text-blue-800',
    debug:   'bg-gray-100 text-gray-600',
};

function RunRow({ run, entries }) {
    const [expanded, setExpanded] = useState(false);

    const hasError   = run.error_count   > 0;
    const hasWarning = run.warning_count > 0;

    const statusColor = hasError
        ? 'border-l-red-500'
        : hasWarning
            ? 'border-l-yellow-400'
            : 'border-l-green-400';

    const statusBadge = hasError
        ? <span className="px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">{run.error_count} error{run.error_count !== 1 ? 's' : ''}</span>
        : hasWarning
            ? <span className="px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">{run.warning_count} warning{run.warning_count !== 1 ? 's' : ''}</span>
            : <span className="px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">OK</span>;

    const lastEntry = entries && entries.length > 0 ? entries[entries.length - 1] : null;

    return (
        <div className={`bg-white border border-gray-200 rounded-lg border-l-4 ${statusColor} overflow-hidden`}>
            {/* Run summary row */}
            <div className="px-4 py-3 flex items-center gap-3">
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        {run.form_id && (
                            <span className="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded">
                                Form {run.form_id}
                            </span>
                        )}
                        {statusBadge}
                        <span className="text-xs text-gray-400">{run.total_count} events</span>
                        {run.info_count > 0 && (
                            <span className="text-xs text-blue-500">{run.info_count} info</span>
                        )}
                    </div>
                    <div className="mt-1 text-sm text-gray-700 truncate">
                        {lastEntry ? lastEntry.message : '—'}
                    </div>
                </div>

                <div className="text-right shrink-0">
                    <div className="text-xs text-gray-400 whitespace-nowrap">{run.finished_at}</div>
                    <button
                        onClick={() => setExpanded(v => !v)}
                        className="mt-1 text-xs text-blue-600 hover:text-blue-800 font-medium"
                    >
                        {expanded ? 'Hide' : 'View more'}
                    </button>
                </div>
            </div>

            {/* Expanded entries */}
            {expanded && entries && entries.length > 0 && (
                <div className="border-t border-gray-100 divide-y divide-gray-50">
                    {entries.map(entry => (
                        <div key={entry.id} className="px-4 py-2 flex items-start gap-3 text-xs hover:bg-gray-50">
                            <span className={`mt-0.5 shrink-0 inline-flex items-center px-1.5 py-0.5 rounded font-medium ${LEVEL_COLORS[entry.level] ?? 'bg-gray-100 text-gray-600'}`}>
                                {entry.level}
                            </span>
                            <div className="flex-1 min-w-0">
                                <div className="text-gray-800">{entry.message}</div>
                                {entry.context && Object.keys(entry.context).length > 0 && (
                                    <ContextSnippet context={entry.context} />
                                )}
                            </div>
                            <div className="shrink-0 text-gray-400 whitespace-nowrap">{entry.logged_at}</div>
                            <Link
                                href={`/logs/${entry.id}`}
                                className="shrink-0 text-blue-500 hover:text-blue-700"
                            >
                                Detail
                            </Link>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function ContextSnippet({ context }) {
    const [open, setOpen] = useState(false);
    const keys = Object.keys(context).filter(k => k !== 'channel' && k !== 'webhook_log_id' && k !== 'form_id' && k !== 'entry_id');
    if (keys.length === 0) return null;
    return (
        <div className="mt-1">
            <button onClick={() => setOpen(v => !v)} className="text-gray-400 hover:text-gray-600 underline text-xs">
                {open ? 'hide context' : `context (${keys.length} field${keys.length !== 1 ? 's' : ''})`}
            </button>
            {open && (
                <pre className="mt-1 bg-gray-50 border border-gray-200 rounded p-2 text-xs text-gray-700 overflow-auto max-h-48 whitespace-pre-wrap break-words">
                    {JSON.stringify(Object.fromEntries(keys.map(k => [k, context[k]])), null, 2)}
                </pre>
            )}
        </div>
    );
}

export default function Runs() {
    const { runs, entriesByRun, filters, currentFilters } = usePage().props;

    const [formId, setFormId] = useState(currentFilters.form_id ?? '');
    const [hours,  setHours]  = useState(currentFilters.hours   ?? '');

    function applyFilters(overrides = {}) {
        const params = { form_id: formId, hours, ...overrides };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get('/logs', params, { preserveState: true, replace: true });
    }

    return (
        <AuthenticatedLayout title="Webhook Run Logs">
            <div className="max-w-5xl mx-auto px-4 py-6 space-y-4">

                {/* Tab switch */}
                <div className="flex items-center gap-3">
                    <span className="text-sm font-medium text-blue-700 px-3 py-1.5 rounded border border-blue-300 bg-blue-50">
                        By Webhook Run
                    </span>
                    <Link href="/logs/all"
                        className="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5 rounded border border-gray-200 hover:bg-gray-50">
                        All Logs
                    </Link>
                </div>

                {/* Filters */}
                <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="flex flex-wrap gap-3 items-end">
                        <select value={formId} onChange={e => { setFormId(e.target.value); applyFilters({ form_id: e.target.value }); }}
                            className="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All forms</option>
                            {filters.form_ids.map(f => <option key={f} value={f}>{f}</option>)}
                        </select>

                        <select value={hours} onChange={e => { setHours(e.target.value); applyFilters({ hours: e.target.value }); }}
                            className="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All time</option>
                            <option value="1">Last 1h</option>
                            <option value="6">Last 6h</option>
                            <option value="24">Last 24h</option>
                            <option value="72">Last 3 days</option>
                        </select>

                        <button onClick={() => {
                            setFormId(''); setHours('');
                            router.get('/logs', {}, { replace: true });
                        }} className="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Clear
                        </button>
                    </div>
                </div>

                {/* Run list */}
                <div className="space-y-3">
                    {runs.data.length === 0 && (
                        <div className="bg-white border border-gray-200 rounded-lg px-6 py-10 text-center text-gray-400 text-sm">
                            No webhook runs found.
                        </div>
                    )}
                    {runs.data.map(run => (
                        <RunRow
                            key={run.webhook_log_id}
                            run={run}
                            entries={entriesByRun[run.webhook_log_id] ?? []}
                        />
                    ))}
                </div>

                {/* Pagination */}
                {runs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-gray-600">
                        <span>Showing {runs.from}–{runs.to} of {runs.total} runs</span>
                        <div className="flex gap-2">
                            {runs.links.map((link, i) => (
                                <button key={i}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                    className={`px-3 py-1 rounded border text-sm ${link.active ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50 disabled:opacity-40'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
