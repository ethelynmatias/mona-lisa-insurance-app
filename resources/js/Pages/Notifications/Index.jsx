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

export default function Index() {
    const { notifications, unreadCount, currentFilter } = usePage().props;
    const [loading, setLoading] = useState(false);

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
                            className={`bg-white border rounded-lg px-5 py-4 flex items-start gap-4 transition-colors
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
                                    <button onClick={() => markRead(item.id)}
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
        </AuthenticatedLayout>
    );
}
