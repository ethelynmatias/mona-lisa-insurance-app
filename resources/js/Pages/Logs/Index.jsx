import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const LEVEL_COLORS = {
    error:   'bg-red-100 text-red-800',
    warning: 'bg-yellow-100 text-yellow-800',
    info:    'bg-blue-100 text-blue-800',
    debug:   'bg-gray-100 text-gray-600',
};

export default function Index() {
    const { logs, stats, filters, currentFilters } = usePage().props;
    const flash = usePage().props.flash ?? {};

    const [search,  setSearch]  = useState(currentFilters.search   ?? '');
    const [level,   setLevel]   = useState(currentFilters.level    ?? '');
    const [channel, setChannel] = useState(currentFilters.channel  ?? '');
    const [formId,  setFormId]  = useState(currentFilters.form_id  ?? '');
    const [hours,   setHours]   = useState(currentFilters.hours    ?? '');

    function applyFilters(overrides = {}) {
        const params = { search, level, channel, form_id: formId, hours, ...overrides };
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
        router.get('/logs', params, { preserveState: true, replace: true });
    }

    function handleClear() {
        if (!confirm('Clear logs older than 30 days?')) return;
        router.delete('/logs/clear', {}, { preserveScroll: true });
    }

    return (
        <AuthenticatedLayout title="Application Logs">
            <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">

                {flash.success && (
                    <div className="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                        {flash.success}
                    </div>
                )}

                {/* Stats */}
                <div className="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    {[
                        { label: 'Total',    value: stats.total,      color: 'text-gray-800' },
                        { label: 'Errors',   value: stats.errors,     color: 'text-red-600'  },
                        { label: 'Warnings', value: stats.warnings,   color: 'text-yellow-600' },
                        { label: 'Info',     value: stats.info,       color: 'text-blue-600' },
                        { label: 'Last 24h', value: stats.recent_24h, color: 'text-purple-600' },
                    ].map(s => (
                        <div key={s.label} className="bg-white border border-gray-200 rounded-lg p-4 text-center">
                            <div className={`text-2xl font-bold ${s.color}`}>{s.value}</div>
                            <div className="text-xs text-gray-500 mt-1">{s.label}</div>
                        </div>
                    ))}
                </div>

                {/* Filters */}
                <div className="bg-white border border-gray-200 rounded-lg p-4">
                    <div className="flex flex-wrap gap-3 items-end">
                        <input
                            type="text"
                            placeholder="Search message..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters()}
                            className="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-56"
                        />

                        <select value={level} onChange={e => { setLevel(e.target.value); applyFilters({ level: e.target.value }); }}
                            className="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All levels</option>
                            {filters.levels.map(l => <option key={l} value={l}>{l}</option>)}
                        </select>

                        <select value={channel} onChange={e => { setChannel(e.target.value); applyFilters({ channel: e.target.value }); }}
                            className="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All channels</option>
                            {filters.channels.map(c => <option key={c} value={c}>{c}</option>)}
                        </select>

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

                        <button onClick={() => applyFilters()}
                            className="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
                            Filter
                        </button>

                        <button onClick={() => {
                            setSearch(''); setLevel(''); setChannel(''); setFormId(''); setHours('');
                            router.get('/logs', {}, { replace: true });
                        }} className="px-4 py-1.5 bg-gray-100 text-gray-700 text-sm rounded-md hover:bg-gray-200">
                            Clear
                        </button>

                        <button onClick={handleClear}
                            className="ml-auto px-4 py-1.5 bg-red-50 text-red-700 text-sm rounded-md hover:bg-red-100 border border-red-200">
                            Clear old logs
                        </button>
                    </div>
                </div>

                {/* Table */}
                <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 w-32">Level</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600">Message</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 w-28">Channel</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 w-24">Form</th>
                                <th className="px-4 py-3 text-left font-medium text-gray-600 w-40">Time</th>
                                <th className="px-4 py-3 w-16"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-gray-400">No logs found.</td>
                                </tr>
                            )}
                            {logs.data.map(log => (
                                <tr key={log.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${LEVEL_COLORS[log.level] ?? 'bg-gray-100 text-gray-600'}`}>
                                            {log.level}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-800 truncate max-w-xs">{log.message}</td>
                                    <td className="px-4 py-3 text-gray-500">{log.channel}</td>
                                    <td className="px-4 py-3 text-gray-500 text-xs">{log.form_id ?? '—'}</td>
                                    <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{log.logged_at}</td>
                                    <td className="px-4 py-3">
                                        <Link href={`/logs/${log.id}`}
                                            className="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-gray-600">
                        <span>Showing {logs.from}–{logs.to} of {logs.total}</span>
                        <div className="flex gap-2">
                            {logs.links.map((link, i) => (
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
