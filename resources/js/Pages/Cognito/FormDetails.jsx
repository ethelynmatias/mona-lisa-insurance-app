import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function DetailRow({ label, value }) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-4 py-3 border-b border-gray-100 last:border-0">
            <span className="text-sm font-medium text-gray-500 sm:w-40 flex-shrink-0">{label}</span>
            <span className="text-sm text-gray-900 break-all">{value ?? '—'}</span>
        </div>
    );
}

export default function FormDetails() {
    const { form, error } = usePage().props;

    const formName = form?.Name ?? form?.name ?? 'Form Details';

    return (
        <AuthenticatedLayout title={formName}>
            <div className="space-y-4">

                {/* Breadcrumb */}
                <nav className="flex items-center gap-2 text-sm text-gray-500">
                    <Link href="/dashboard" className="hover:text-blue-600 transition-colors">
                        Dashboard
                    </Link>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                    <span className="text-gray-900 font-medium truncate">{formName}</span>
                </nav>

                {/* Error */}
                {error && (
                    <div className="flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        <svg className="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{error}</span>
                    </div>
                )}

                {/* Form details card */}
                {form && (
                    <div className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-4 border-b border-gray-100">
                            <h2 className="text-sm font-semibold text-gray-900">Form Details</h2>
                        </div>
                        <div className="px-5">
                            {Object.entries(form)
                                .filter(([key]) => !key.startsWith('$'))
                                .map(([key, value]) => (
                                    <DetailRow
                                        key={key}
                                        label={key}
                                        value={typeof value === 'object' ? JSON.stringify(value) : String(value ?? '—')}
                                    />
                                ))
                            }
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
