import { useState } from 'react';

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

export default function WebhookPayloadModal({ webhook, onClose }) {
    const [copied, setCopied] = useState(false);
    const json = JSON.stringify(webhook.payload, null, 2);

    function handleCopy() {
        navigator.clipboard.writeText(json);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            {/* Backdrop */}
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />

            {/* Modal */}
            <div className="relative bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">

                {/* Header */}
                <div className="flex items-start justify-between px-6 py-4 border-b border-gray-100">
                    <div>
                        <h3 className="text-sm font-semibold text-gray-900">Webhook Payload</h3>
                        <p className="text-xs text-gray-400 mt-0.5">
                            <span className={`inline-block text-xs font-medium px-2 py-0.5 rounded-full mr-2
                                ${EVENT_STYLES[webhook.event_type] ?? 'bg-gray-100 text-gray-600'}`}>
                                {eventLabel(webhook.event_type)}
                            </span>
                            {webhook.entry_id && <span className="font-mono">Entry #{webhook.entry_id} · </span>}
                            {formatDate(webhook.created_at)}
                        </p>
                    </div>
                    <div className="flex items-center gap-2 ml-4 flex-shrink-0">
                        <button
                            onClick={handleCopy}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600
                                bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            {copied ? (
                                <>
                                    <svg className="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                    Copied
                                </>
                            ) : (
                                <>
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    Copy
                                </>
                            )}
                        </button>
                        <button
                            onClick={onClose}
                            className="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                            aria-label="Close"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {/* Body */}
                <div className="overflow-y-auto p-6 space-y-4">

                    {/* Sync result */}
                    {webhook.sync_status && (
                        <div className={`flex flex-wrap items-start gap-3 px-4 py-3 rounded-lg text-xs border
                            ${webhook.sync_status === 'synced'  ? 'bg-green-50 border-green-200 text-green-700'    : ''}
                            ${webhook.sync_status === 'failed'  ? 'bg-red-50 border-red-200 text-red-700'          : ''}
                            ${webhook.sync_status === 'skipped' ? 'bg-gray-50 border-gray-200 text-gray-500'       : ''}
                            ${webhook.sync_status === 'pending' ? 'bg-yellow-50 border-yellow-200 text-yellow-700' : ''}
                        `}>
                            <span className="font-semibold capitalize">NowCerts sync: {webhook.sync_status}</span>
                            {webhook.synced_entities?.length > 0 && (
                                <span>Entities pushed: {webhook.synced_entities.join(', ')}</span>
                            )}
                            {webhook.sync_error && (
                                <span className="w-full mt-1 text-red-600">{webhook.sync_error}</span>
                            )}
                        </div>
                    )}

                    {/* Raw payload */}
                    {webhook.payload ? (
                        <pre className="text-xs text-gray-700 bg-gray-50 rounded-lg p-4 overflow-x-auto whitespace-pre-wrap break-all">
                            {json}
                        </pre>
                    ) : (
                        <p className="text-sm text-gray-400 text-center py-8">No payload recorded.</p>
                    )}
                </div>
            </div>
        </div>
    );
}
