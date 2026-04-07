import { useState, useMemo } from 'react';
import { Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SchemaField from '@/Components/SchemaField';
import SearchInput from '@/Components/SearchInput';
import Pagination from '@/Components/Pagination';

const PER_PAGE = 10;

export default function FormDetails() {
    const { form, fields = [], error } = usePage().props;

    const formName = form?.Name ?? form?.name ?? 'Form Details';
    const formId   = form?.Id   ?? form?.id   ?? '';

    const [search, setSearch]     = useState('');
    const [currentPage, setPage]  = useState(1);

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

    const totalPages  = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
    const safePage    = Math.min(currentPage, totalPages);
    const paginated   = filtered.slice((safePage - 1) * PER_PAGE, safePage * PER_PAGE);

    function handleSearch(value) {
        setSearch(value);
        setPage(1);
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

                        {/* Schema */}
                        <div className="bg-white rounded-xl border border-gray-200">
                            <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                                <div className="flex-1">
                                    <h2 className="text-sm font-semibold text-gray-900">Form Schema</h2>
                                    <p className="text-xs text-gray-400 mt-0.5">
                                        {filtered.length} of {fields.length} field{fields.length !== 1 ? 's' : ''}
                                    </p>
                                </div>
                                <div className="w-full sm:w-64">
                                    <SearchInput
                                        value={search}
                                        onChange={handleSearch}
                                        placeholder="Search fields…"
                                    />
                                </div>
                            </div>

                            <div className="px-5 py-2 min-h-[4rem]">
                                {paginated.length === 0 ? (
                                    <p className="text-sm text-gray-400 py-8 text-center">No fields found</p>
                                ) : (
                                    paginated.map((field, i) => (
                                        <SchemaField key={i} field={field} />
                                    ))
                                )}
                            </div>

                            <Pagination
                                pagination={{
                                    currentPage: safePage,
                                    perPage: PER_PAGE,
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
