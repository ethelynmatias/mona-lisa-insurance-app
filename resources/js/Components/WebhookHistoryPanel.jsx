const EVENT_STYLES = {
    'entry.submitted': 'bg-blue-100 text-blue-700',
    'entry.updated':   'bg-amber-100 text-amber-700',
    'entry.deleted':   'bg-red-100 text-red-700',
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

export default function WebhookHistoryPanel({ webhooks = [], showFormColumn = false }) {
    return (
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
                <span className="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-500">
                    <span className="w-1.5 h-1.5 rounded-full bg-gray-400" />
                    Live
                </span>
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
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-50">
                            {webhooks.map(wh => (
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
                                        <span className="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700">
                                            <span className="w-1.5 h-1.5 rounded-full bg-green-500" />
                                            {wh.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                        {formatDate(wh.created_at)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
