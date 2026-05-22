import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const STATUS_COLORS = {
    synced:  'bg-green-100 text-green-700',
    failed:  'bg-red-100 text-red-700',
    skipped: 'bg-yellow-100 text-yellow-700',
    pending: 'bg-gray-100 text-gray-600',
};

const EVENT_LABELS = {
    'entry.submitted': 'Submitted',
    'entry.updated':   'Updated',
    'entry.deleted':   'Deleted',
};

function PayloadModal({ item, onClose }) {
    if (!item) return null;

    const sections = [
        {
            label: 'Payload',
            value: item.payload,
            show: item.payload && Object.keys(item.payload).length > 0,
        },
        {
            label: 'Synced Entities',
            value: item.synced_entities,
            show: item.synced_entities?.length > 0,
        },
        {
            label: 'Uploaded File IDs',
            value: item.uploaded_file_ids,
            show: item.uploaded_file_ids?.length > 0,
        },
        {
            label: 'Synced NowCerts IDs',
            value: item.synced_nowcerts_ids,
            show: item.synced_nowcerts_ids && Object.keys(item.synced_nowcerts_ids).length > 0,
        },
    ];

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />

            {/* Panel */}
            <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col">
                {/* Header */}
                <div className="flex items-start justify-between px-6 py-4 border-b border-gray-200">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900">
                            {item.form_name ?? item.form_id}
                        </h3>
                        <div className="flex items-center gap-2 mt-1 flex-wrap">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                ${STATUS_COLORS[item.sync_status] ?? 'bg-gray-100 text-gray-600'}`}>
                                {item.sync_status}
                            </span>
                            <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">
                                {EVENT_LABELS[item.event_type] ?? item.event_type}
                            </span>
                            {item.entry_id && (
                                <span className="text-xs text-gray-500">Entry #{item.entry_id}</span>
                            )}
                            <span className="text-xs text-gray-400">{item.created_at}</span>
                        </div>
                    </div>
                    <button onClick={onClose}
                        className="ml-4 text-gray-400 hover:text-gray-600 transition-colors text-xl leading-none"
                        aria-label="Close">
                        &times;
                    </button>
                </div>

                {/* Body */}
                <div className="overflow-y-auto px-6 py-4 space-y-5 flex-1">
                    {item.sync_error && (
                        <div className="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            <p className="text-xs font-semibold text-red-700 uppercase tracking-wide mb-1">Sync Error</p>
                            <p className="text-sm text-red-600 break-words">{item.sync_error}</p>
                        </div>
                    )}

                    {item.synced_at && (
                        <div>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Synced At</p>
                            <p className="text-sm text-gray-700">{item.synced_at}</p>
                        </div>
                    )}

                    {sections.map(({ label, value, show }) => show && (
                        <div key={label}>
                            <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{label}</p>
                            <pre className="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs text-gray-800
                                overflow-x-auto whitespace-pre-wrap break-words leading-relaxed">
                                {JSON.stringify(value, null, 2)}
                            </pre>
                        </div>
                    ))}

                    {!item.sync_error && sections.every(s => !s.show) && !item.synced_at && (
                        <p className="text-sm text-gray-400 text-center py-6">No additional details available.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index() {
    const { notifications, unreadCount, currentFilter } = usePage().props;
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(null);

    function setFilter(filter) {
        router.get('/notifications', filter === 'all' ? {} : { filter }, { preserveState: true, replace: true });
    }

    function markRead(id) {
        router.patch(`/notifications/${id}/read`, {}, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['notifications', 'unreadCount'] }),
        });
    }

    function markAllRead() {
        if (!confirm('Mark all notifications as read?')) return;
        setLoading(true);
        router.patch('/notifications/read-all', {}, {
            preserveScroll: true,
            onFinish: () => setLoading(false),
            onSuccess: () => router.reload({ only: ['notifications', 'unreadCount'] }),
        });
    }

    return (
        <AuthenticatedLayout title="Notifications">
            <div className="max-w-4xl mx-auto px-4 py-6 space-y-4">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <h2 className="text-lg font-semibold text-gray-800">Webhook Notifications</h2>
                        {unreadCount > 0 && (
                            <span className="bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {unreadCount} new
                            </span>
                        )}
                    </div>
                    {unreadCount > 0 && (
                        <button onClick={markAllRead} disabled={loading}
                            className="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50">
                            Mark all as read
                        </button>
                    )}
                </div>

                {/* Filter tabs */}
                <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
                    {['all', 'unread', 'read'].map(f => (
                        <button key={f} onClick={() => setFilter(f)}
                            className={`px-4 py-1.5 rounded-md text-sm font-medium capitalize transition-colors
                                ${currentFilter === f
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'
                                }`}>
                            {f}
                        </button>
                    ))}
                </div>

                {/* List */}
                <div className="space-y-2">
                    {notifications.data.length === 0 && (
                        <div className="bg-white border border-gray-200 rounded-lg px-6 py-12 text-center text-gray-400">
                            No notifications found.
                        </div>
                    )}

                    {notifications.data.map(item => (
                        <div key={item.id}
                            onClick={() => setSelected(item)}
                            className={`bg-white border rounded-lg px-5 py-4 flex items-start gap-4 transition-colors
                                cursor-pointer hover:shadow-sm
                                ${!item.read_at ? 'border-blue-200 bg-blue-50/30' : 'border-gray-200'}`}>

                            {/* Unread dot */}
                            <div className="mt-1.5 flex-shrink-0">
                                {!item.read_at
                                    ? <div className="w-2.5 h-2.5 rounded-full bg-blue-500" />
                                    : <div className="w-2.5 h-2.5 rounded-full bg-gray-200" />
                                }
                            </div>

                            {/* Content */}
                            <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <span className="font-medium text-gray-900 text-sm">
                                        {item.form_name ?? item.form_id}
                                    </span>
                                    <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        ${STATUS_COLORS[item.sync_status] ?? 'bg-gray-100 text-gray-600'}`}>
                                        {item.sync_status}
                                    </span>
                                    <span className="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">
                                        {EVENT_LABELS[item.event_type] ?? item.event_type}
                                    </span>
                                </div>

                                <div className="mt-1 text-sm text-gray-500 flex items-center gap-3 flex-wrap">
                                    {item.entry_id && <span>Entry #{item.entry_id}</span>}
                                    {item.sync_error && (
                                        <span className="text-red-500 truncate max-w-xs">{item.sync_error}</span>
                                    )}
                                </div>

                                <div className="mt-1 text-xs text-gray-400">{item.created_at}</div>
                            </div>

                            {/* Actions */}
                            <div className="flex-shrink-0 flex items-center gap-3">
                                {!item.read_at && (
                                    <button
                                        onClick={e => { e.stopPropagation(); markRead(item.id); }}
                                        className="text-xs text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap">
                                        Mark read
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {/* Pagination */}
                {notifications.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm text-gray-600">
                        <span>Showing {notifications.from}–{notifications.to} of {notifications.total}</span>
                        <div className="flex gap-2">
                            {notifications.links.map((link, i) => (
                                <button key={i}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                    className={`px-3 py-1 rounded border text-sm
                                        ${link.active
                                            ? 'bg-blue-600 text-white border-blue-600'
                                            : 'border-gray-300 hover:bg-gray-50 disabled:opacity-40'
                                        }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>

            <PayloadModal item={selected} onClose={() => setSelected(null)} />
        </AuthenticatedLayout>
    );
}
