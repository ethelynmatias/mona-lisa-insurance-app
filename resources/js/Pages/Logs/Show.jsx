import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const LEVEL_COLORS = {
    error:   'bg-red-100 text-red-800',
    warning: 'bg-yellow-100 text-yellow-800',
    info:    'bg-blue-100 text-blue-800',
    debug:   'bg-gray-100 text-gray-600',
};

export default function Show() {
    const { log } = usePage().props;

    return (
        <AuthenticatedLayout title="Log Entry">
            <div className="max-w-4xl mx-auto px-4 py-6 space-y-6">

                <div className="flex items-center gap-3">
                    <Link href="/logs" className="text-blue-600 hover:text-blue-800 text-sm">
                        ← Back to Logs
                    </Link>
                </div>

                <div className="bg-white border border-gray-200 rounded-lg divide-y divide-gray-100">
                    {/* Header */}
                    <div className="px-6 py-4 flex items-center gap-4">
                        <span className={`inline-flex items-center px-2.5 py-1 rounded text-xs font-medium ${LEVEL_COLORS[log.level] ?? 'bg-gray-100 text-gray-600'}`}>
                            {log.level}
                        </span>
                        <span className="text-gray-400 text-sm">{log.logged_at}</span>
                        {log.form_id && (
                            <span className="text-gray-500 text-sm">Form: <span className="font-mono">{log.form_id}</span></span>
                        )}
                    </div>

                    {/* Message */}
                    <div className="px-6 py-4">
                        <div className="text-xs font-semibold text-gray-400 uppercase mb-1">Message</div>
                        <div className="text-gray-800 font-medium">{log.message}</div>
                    </div>

                    {/* Meta */}
                    <div className="px-6 py-4 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-xs font-semibold text-gray-400 uppercase mb-1">Channel</div>
                            <div className="text-gray-700">{log.channel}</div>
                        </div>
                        <div>
                            <div className="text-xs font-semibold text-gray-400 uppercase mb-1">IP Address</div>
                            <div className="text-gray-700">{log.ip_address ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-xs font-semibold text-gray-400 uppercase mb-1">Request ID</div>
                            <div className="font-mono text-gray-700 text-xs">{log.request_id ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-xs font-semibold text-gray-400 uppercase mb-1">Webhook Log ID</div>
                            <div className="text-gray-700">{log.webhook_log_id ?? '—'}</div>
                        </div>
                    </div>

                    {/* Context */}
                    {log.context && Object.keys(log.context).length > 0 && (
                        <div className="px-6 py-4">
                            <div className="text-xs font-semibold text-gray-400 uppercase mb-2">Context</div>
                            <pre className="bg-gray-50 border border-gray-200 rounded-md p-4 text-xs text-gray-800 overflow-auto whitespace-pre-wrap break-words max-h-[600px]">
                                {JSON.stringify(log.context, null, 2)}
                            </pre>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
