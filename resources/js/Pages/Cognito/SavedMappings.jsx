import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function SavedMappings() {
    const { form, formId, mappings = [] } = usePage().props;

    const formName = form?.Name ?? form?.name ?? `Form ${formId}`;

    const grouped = mappings.reduce((acc, row) => {
        const entity = row.nowcerts_entity;
        if (!acc[entity]) acc[entity] = [];
        acc[entity].push(row);
        return acc;
    }, {});

    return (
        <AuthenticatedLayout title="Saved Mappings">
            <div className="space-y-4">

                {/* Breadcrumb */}
                <nav className="flex items-center gap-2 text-sm text-gray-500">
                    <Link href="/dashboard" className="hover:text-blue-600 transition-colors">
                        Dashboard
                    </Link>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                    <Link
                        href={route('forms.show', { formId })}
                        className="hover:text-blue-600 transition-colors truncate"
                    >
                        {formName}
                    </Link>
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                    <span className="text-gray-900 font-medium">Saved Mappings</span>
                </nav>

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-base font-semibold text-gray-900">Saved Mappings</h1>
                        <p className="text-xs text-gray-500 mt-0.5">{formName} — {mappings.length} field{mappings.length !== 1 ? 's' : ''} configured</p>
                    </div>
                    <Link
                        href={route('forms.show', { formId })}
                        className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600
                            border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-gray-900 transition-colors"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Mappings
                    </Link>
                </div>

                {/* Empty state */}
                {mappings.length === 0 && (
                    <div className="bg-white rounded-xl border border-gray-200 px-5 py-12 text-center">
                        <svg className="w-10 h-10 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p className="text-sm text-gray-500">No mappings saved yet.</p>
                        <Link
                            href={route('forms.show', { formId })}
                            className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700"
                        >
                            Configure mappings
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                )}

                {/* Grouped by entity */}
                {Object.entries(grouped).map(([entity, rows]) => (
                    <div key={entity} className="bg-white rounded-xl border border-gray-200">
                        <div className="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700">
                                {entity}
                            </span>
                            <span className="text-xs text-gray-400">{rows.length} field{rows.length !== 1 ? 's' : ''}</span>
                        </div>
                        <table className="w-full text-left">
                            <thead>
                                <tr className="bg-gray-50/50 border-b border-gray-100">
                                    <th className="px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide w-1/2">
                                        Cognito Field
                                    </th>
                                    <th className="px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                        NowCerts Field
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {rows.map(row => (
                                    <tr key={row.cognito_field} className="hover:bg-gray-50/50">
                                        <td className="px-5 py-2.5 text-xs font-mono text-gray-700 break-all">
                                            {row.cognito_field}
                                        </td>
                                        <td className="px-5 py-2.5 text-xs text-gray-600">
                                            {row.nowcerts_field}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ))}

            </div>
        </AuthenticatedLayout>
    );
}
