import { useState, useMemo } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SchemaField from '@/Components/SchemaField';
import SearchInput from '@/Components/SearchInput';
import Pagination from '@/Components/Pagination';
import WebhookHistoryPanel from '@/Components/WebhookHistoryPanel';

const PER_PAGE_OPTIONS = [20, 50, 100];

export default function FormDetails() {
    const { form, fields = [], mappingLookup = {}, availableFields = {}, availableFieldsError = null, webhooks = [], error } = usePage().props;
    const flash = usePage().props.flash ?? {};

    const formName = form?.Name ?? form?.name ?? 'Form Details';
    const formId   = form?.Id   ?? form?.id   ?? '';

    const [search, setSearch]     = useState('');
    const [currentPage, setPage]  = useState(1);
    const [perPage, setPerPage]   = useState(PER_PAGE_OPTIONS[0]);
    const [mappings, setMappings] = useState(mappingLookup);
    const [saving, setSaving]     = useState(false);

    const filtered = useMemo(() => {
        if (!search.trim()) return fields;
        const q = search.toLowerCase();
        return fields.filter(f =>
            (f.Name         ?? f.name         ?? '').toLowerCase().includes(q) ||
            (f.InternalName ?? f.internalName ?? '').toLowerCase().includes(q) ||
            (f.Type         ?? f.type         ?? '').toLowerCase().includes(q) ||
            (f.FieldType    ?? f.fieldType    ?? '').toLowerCase().includes(q) ||
            (f.PropertyType ?? f.propertyType ?? '').toLowerCase().includes(q)
        );
    }, [fields, search]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
    const safePage   = Math.min(currentPage, totalPages);
    const paginated  = filtered.slice((safePage - 1) * perPage, safePage * perPage);

    function handleSearch(value) {
        setSearch(value);
        setPage(1);
    }

    function handlePerPageChange(value) {
        setPerPage(Number(value));
        setPage(1);
    }

    function handleMappingChange(cognitoField, mapping) {
        setMappings(prev => ({ ...prev, [cognitoField]: mapping }));
    }

    function handleSave() {
        setSaving(true);

        // Collect all fields (including nested) with their current mappings
        const allFields = flattenFields(fields);
        const payload   = allFields.map(f => {
            const key     = f.InternalName ?? f.internalName ?? f.Name ?? f.name;
            const mapping = mappings[key] ?? null;
            return {
                cognito_field:   key,
                nowcerts_entity: mapping?.entity ?? null,
                nowcerts_field:  mapping?.field  ?? null,
            };
        });

        router.post(
            route('forms.mappings.save', { formId }),
            { mappings: payload },
            {
                preserveScroll: true,
                preserveState:  true,
                onFinish:       () => setSaving(false),
            }
        );
    }

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

                {/* Success */}
                {flash.success && (
                    <div className="flex items-center gap-3 px-4 py-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                        <svg className="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{flash.success}</span>
                    </div>
                )}

                {form && (
                    <div className="space-y-4">

                        {/* Form info */}
                        <div className="bg-white rounded-xl border border-gray-200 p-5">
                            <h2 className="text-sm font-semibold text-gray-900 mb-4">Form Info</h2>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

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
                        </div>

                        {/* NowCerts fields error */}
                        {availableFieldsError && (
                            <div className="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-700">
                                <svg className="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                </svg>
                                <div>
                                    <p className="font-medium">NowCerts fields unavailable</p>
                                    <p className="mt-0.5 text-amber-600">{availableFieldsError}</p>
                                </div>
                            </div>
                        )}

                        {/* Webhook History */}
                        <WebhookHistoryPanel
                            webhooks={webhooks}
                            clearRoute={route('webhook.history.clear-form', { formId })}
                        />

                        {/* Schema */}
                        <div className="bg-white rounded-xl border border-gray-200">

                            {/* Header */}
                            <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1">
                                    <h2 className="text-sm font-semibold text-gray-900">Form Schema</h2>
                                    <p className="text-xs text-gray-400 mt-0.5">
                                        {filtered.length} of {fields.length} field{fields.length !== 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
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
                                    <div className="w-full sm:w-64">
                                        <SearchInput
                                            value={search}
                                            onChange={handleSearch}
                                            placeholder="Search fields…"
                                        />
                                    </div>
                                    <button
                                        onClick={handleSave}
                                        disabled={saving || !!availableFieldsError || Object.keys(availableFields).length === 0}
                                        className="flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white
                                            text-sm font-medium rounded-lg hover:bg-blue-700 disabled:opacity-60
                                            disabled:cursor-not-allowed transition-colors"
                                    >
                                        {saving ? (
                                            <>
                                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                    <path className="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Saving…
                                            </>
                                        ) : (
                                            <>
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                        d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                                </svg>
                                                Save Mappings
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>

                            {/* Table */}
                            <div className="overflow-x-auto">
                                {paginated.length === 0 ? (
                                    <p className="text-sm text-gray-400 py-8 text-center">No fields found</p>
                                ) : (
                                    <table className="w-full text-left">
                                        <thead>
                                            <tr className="border-b border-gray-100 bg-gray-50/50">
                                                <th className="px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-1/5">
                                                    Field Name
                                                </th>
                                                <th className="py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                    Cognito Form Fields
                                                </th>
                                                <th className="py-3 pr-5 text-xs font-semibold text-gray-500 uppercase tracking-wide w-56">
                                                    NowCerts Field
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {paginated.map((field, i) => (
                                                <SchemaField
                                                    key={i}
                                                    field={field}
                                                    mappings={mappings}
                                                    availableFields={availableFields}
                                                    onChange={handleMappingChange}
                                                />
                                            ))}
                                        </tbody>
                                    </table>
                                )}
                            </div>

                            <Pagination
                                pagination={{
                                    currentPage: safePage,
                                    perPage,
                                    total: filtered.length,
                                    totalPages,
                                }}
                                onPageChange={setPage}
                                label="fields"
                            />
                        </div>

                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function flattenFields(fields, result = []) {
    for (const field of fields) {
        result.push(field);
        const children = field.Children ?? field.children ?? field.Fields ?? field.fields ?? [];
        if (children.length > 0) flattenFields(children, result);
    }
    return result;
}
