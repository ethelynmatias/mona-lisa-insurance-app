import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function SchemaField({ field, depth = 0 }) {
    const name     = field.Name       ?? field.name       ?? '—';
    const type     = field.Type       ?? field.type       ?? '—';
    const required = field.Required   ?? field.required   ?? false;
    const children = field.Children   ?? field.children   ?? field.Fields ?? field.fields ?? [];

    return (
        <div className={depth > 0 ? 'ml-5 border-l-2 border-gray-100 pl-4' : ''}>
            <div className="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <div className="flex-1 min-w-0">
                    <span className="text-sm font-medium text-gray-900">{name}</span>
                    {required && (
                        <span className="ml-2 text-xs text-red-500 font-medium">required</span>
                    )}
                </div>
                <span className="text-xs font-mono px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                    {type}
                </span>
            </div>
            {children.length > 0 && children.map((child, i) => (
                <SchemaField key={i} field={child} depth={depth + 1} />
            ))}
        </div>
    );
}

export default function FormDetails() {
    const { form, schema, error } = usePage().props;

    const formName = form?.Name ?? form?.name ?? 'Form Details';
    const formId   = form?.Id   ?? form?.id   ?? '';
    const fields   = Array.isArray(schema)
        ? schema
        : (schema?.Fields ?? schema?.fields ?? []);

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

                {form && (
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">

                        {/* Form info */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5 flex flex-col gap-3">
                            <h2 className="text-sm font-semibold text-gray-900">Form Info</h2>

                            <div>
                                <p className="text-xs text-gray-500 mb-0.5">Form Name</p>
                                <p className="text-sm font-semibold text-gray-900">{formName}</p>
                            </div>

                            <div>
                                <p className="text-xs text-gray-500 mb-0.5">Form ID</p>
                                <p className="text-sm font-mono text-gray-600 break-all">{formId}</p>
                            </div>

                            {form.IsAvailable !== undefined && (
                                <div>
                                    <p className="text-xs text-gray-500 mb-0.5">Status</p>
                                    <span className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium
                                        ${form.IsAvailable ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                                        <span className={`w-1.5 h-1.5 rounded-full ${form.IsAvailable ? 'bg-green-500' : 'bg-gray-400'}`} />
                                        {form.IsAvailable ? 'Active' : 'Inactive'}
                                    </span>
                                </div>
                            )}

                            {(form.EntryCount ?? form.entryCount) !== undefined && (
                                <div>
                                    <p className="text-xs text-gray-500 mb-0.5">Total Entries</p>
                                    <p className="text-sm font-semibold text-gray-900">
                                        {Number(form.EntryCount ?? form.entryCount).toLocaleString()}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Schema */}
                        <div className="lg:col-span-2 bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h2 className="text-sm font-semibold text-gray-900">Form Schema</h2>
                                <span className="text-xs text-gray-400">{fields.length} field{fields.length !== 1 ? 's' : ''}</span>
                            </div>

                            <div className="px-5 py-2">
                                {fields.length === 0 ? (
                                    <p className="text-sm text-gray-400 py-8 text-center">No schema fields available</p>
                                ) : (
                                    fields.map((field, i) => (
                                        <SchemaField key={i} field={field} />
                                    ))
                                )}
                            </div>
                        </div>

                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
