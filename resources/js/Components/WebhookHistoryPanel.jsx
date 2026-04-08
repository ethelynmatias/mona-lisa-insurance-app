import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import Pagination from '@/Components/Pagination';
import WebhookPayloadModal from '@/Components/Modals/WebhookPayloadModal';
import WebhookRerunModal from '@/Components/Modals/WebhookRerunModal';

const PER_PAGE_OPTIONS = [20, 50, 100];

const EVENT_STYLES = {
    'entry.submitted': 'bg-blue-100 text-blue-700',
    'entry.updated':   'bg-amber-100 text-amber-700',
    'entry.deleted':   'bg-red-100 text-red-700',
};

const SYNC_STYLES = {
    synced:  'bg-green-100 text-green-700',
    failed:  'bg-red-100 text-red-700',
    skipped: 'bg-gray-100 text-gray-500',
    pending: 'bg-yellow-100 text-yellow-700',
};

function eventLabel(type) {
    const map = {
        'entry.submitted': 'Submitted',
        'entry.updated':   'Updated',
        'entry.deleted':   'Deleted',
    };
    return map[type] ?? type;
}

function formatDate(iso) {
    const d = new Date(iso);
    return d.toLocaleString(undefined, {
        month:  'short',
        day:    'numeric',
        year:   'numeric',
        hour:   '2-digit',
        minute: '2-digit',
    });
}

export default function WebhookHistoryPanel({ webhooks = [], showFormColumn = false, clearRoute = null }) {
    const [selected, setSelected]       = useState(null);
    const [rerunTarget, setRerunTarget] = useState(null);
    const [currentPage, setPage]        = useState(1);
    const [perPage, setPerPage]         = useState(PER_PAGE_OPTIONS[0]);

    const totalPages = Math.max(1, Math.ceil(webhooks.length / perPage));
    const safePage   = Math.min(currentPage, totalPages);
    const paginated  = useMemo(
        () => webhooks.slice((safePage - 1) * perPage, safePage * perPage),
        [webhooks, safePage, perPage]
    );

    function handlePerPageChange(value) {
        setPerPage(Number(value));
        setPage(1);
    }

    function handleClear() {
        if (!confirm('Clear all webhook history? This cannot be undone.')) return;
        router.delete(clearRoute, { preserveScroll: true });
    }

    return (
        <>
            <div className="bg-white rounded-xl border border-gray-200">
                <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 className="text-sm font-semibold text-gray-900">Webhook History</h2>
                        <p className="text-xs text-gray-400 mt-0.5">
                            {webhooks.length === 0
                                ? 'No webhooks received yet'
                                : `${webhooks.length} most recent event${webhooks.length !== 1 ? 's' : ''}`}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {webhooks.length > 0 && (
                            <div className="flex items-center gap-2">
                                <label className="text-xs text-gray-500 whitespace-nowrap">Show</label>
                                <select
                                    value={perPage}
                                    onChange={e => handlePerPageChange(e.target.value)}
                                    className="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-gray-700
                                        focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    {PER_PAGE_OPTIONS.map(n => (
                                        <option key={n} value={n}>{n} per page</option>
                                    ))}
                                </select>
                            </div>
                        )}
                        {clearRoute && webhooks.length > 0 && (
                            <button
                                onClick={handleClear}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-600
                                    bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                            >
                                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                Clear History
                            </button>
                        )}
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-500">
                            <span className="w-1.5 h-1.5 rounded-full bg-gray-400" />
                            Live
                        </span>
                    </div>
                </div>

                {webhooks.length === 0 ? (
                    <div className="py-14 flex flex-col items-center gap-3 text-gray-400">
                        <svg className="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <p className="text-sm">No webhook events yet</p>
                        <p className="text-xs text-center max-w-xs">
                            Configure your Cognito Forms webhook to post to{' '}
                            <code className="bg-gray-100 px-1 py-0.5 rounded text-gray-600">/webhook/cognito?form_id=YOUR_FORM_ID</code>
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/50">
                                    {showFormColumn && (
                                        <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Form</th>
                                    )}
                                    <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Event</th>
                                    <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Entry ID</th>
                                    <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                                    <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Received</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {paginated.map(wh => (
                                    <tr key={wh.id} className="hover:bg-gray-50/50 transition-colors">
                                        {showFormColumn && (
                                            <td className="px-5 py-3">
                                                <span className="text-gray-900 font-medium">
                                                    {wh.form_name ?? wh.form_id}
                                                </span>
                                                {wh.form_name && (
                                                    <span className="block text-xs text-gray-400 font-mono">
                                                        {wh.form_id}
                                                    </span>
                                                )}
                                            </td>
                                        )}
                                        <td className="px-5 py-3">
                                            <span className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full capitalize
                                                ${EVENT_STYLES[wh.event_type] ?? 'bg-gray-100 text-gray-600'}`}>
                                                {eventLabel(wh.event_type)}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 font-mono text-xs text-gray-500">
                                            {wh.entry_id ?? <span className="text-gray-300">—</span>}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className={`inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full capitalize
                                                ${SYNC_STYLES[wh.sync_status] ?? 'bg-gray-100 text-gray-500'}`}>
                                                {wh.sync_status ?? wh.status}
                                            </span>
                                            {wh.synced_entities?.length > 0 && (
                                                <span className="block text-xs text-gray-400 mt-0.5">
                                                    {wh.synced_entities.join(', ')}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                            {formatDate(wh.created_at)}
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                {wh.sync_status !== 'skipped' && wh.event_type !== 'entry.deleted' && (
                                                    <button
                                                        onClick={() => setRerunTarget(wh)}
                                                        className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-amber-600
                                                            bg-amber-50 hover:bg-amber-100 rounded-lg transition-colors whitespace-nowrap"
                                                    >
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        Rerun Sync
                                                    </button>
                                                )}
                                                <button
                                                    onClick={() => setSelected(wh)}
                                                    className="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-blue-600
                                                        bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                                                >
                                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pagination
                    pagination={{
                        currentPage: safePage,
                        perPage,
                        total: webhooks.length,
                        totalPages,
                    }}
                    onPageChange={setPage}
                    label="events"
                />
            </div>

            {selected && (
                <WebhookPayloadModal webhook={selected} onClose={() => setSelected(null)} />
            )}

            {rerunTarget && (
                <WebhookRerunModal
                    webhook={rerunTarget}
                    onClose={() => setRerunTarget(null)}
                    onConfirm={() => {
                        router.post(
                            route('webhook.history.rerun', { log: rerunTarget.id }),
                            {},
                            { preserveScroll: true }
                        );
                        setRerunTarget(null);
                    }}
                />
            )}
        </>
    );
}
