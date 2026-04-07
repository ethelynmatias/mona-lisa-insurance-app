import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SearchInput from '@/Components/SearchInput';
import Pagination from '@/Components/Pagination';

const SKIP_KEYS = new Set(['$id', '$version', 'Entry', 'EntryId']);

function getColumns(entries) {
    if (!entries.length) return [];
    const keys = Object.keys(entries[0]).filter((k) => !SKIP_KEYS.has(k) && !k.startsWith('$'));
    return keys.slice(0, 6);
}

function cellValue(value) {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
}

export default function FormEntries() {
    const { form, entries, search: initialSearch, pagination, error } = usePage().props;

    const [search, setSearch] = useState(initialSearch ?? '');

    const formId = form?.Id ?? form?.id ?? '';
    const formName = form?.Name ?? form?.name ?? 'Form';

    const navigate = (params) => {
        router.get(`/dashboard/forms/${formId}`, { search, page: 1, ...params }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearch = (value) => {
        setSearch(value);
        navigate({ search: value, page: 1 });
    };

    const handlePageChange = (page) => {
        navigate({ page });
    };

    const columns = getColumns(entries);

    return (
        <AuthenticatedLayout title={formName}>
            <div className="space-y-4">

                {/* Breadcrumb + header */}
                <div>
                    <nav className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                        <Link href="/dashboard" className="hover:text-blue-600 transition-colors">
                            Dashboard
                        </Link>
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                        <span className="text-gray-900 font-medium truncate">{formName}</span>
                    </nav>
                    <h2 className="text-lg font-semibold text-gray-900">{formName}</h2>
                    <p className="text-sm text-gray-500 mt-0.5">
                        Form ID: <span className="font-mono">{formId}</span>
                    </p>
                </div>

                {/* Card */}
                <div className="bg-white rounded-xl border border-gray-200">

                    {/* Toolbar */}
                    <div className="px-5 py-4 border-b border-gray-100">
                        <SearchInput
                            value={search}
                            onChange={handleSearch}
                            placeholder="Search entries…"
                            className="w-full sm:max-w-xs"
                        />
                    </div>

                    {/* Error */}
                    {error && (
                        <div className="mx-5 my-4 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                            <svg className="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Table */}
                    {!error && (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left text-xs text-gray-500 border-b border-gray-100 bg-gray-50">
                                        <th className="px-5 py-3 font-medium">#</th>
                                        {columns.map((col) => (
                                            <th key={col} className="px-5 py-3 font-medium whitespace-nowrap">
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {entries.length === 0 ? (
                                        <tr>
                                            <td colSpan={columns.length + 1} className="px-5 py-16 text-center">
                                                <svg className="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                <p className="text-gray-400 text-sm">No entries found</p>
                                                {search && (
                                                    <button
                                                        onClick={() => handleSearch('')}
                                                        className="mt-2 text-sm text-blue-600 hover:underline"
                                                    >
                                                        Clear search
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        entries.map((entry, index) => {
                                            const entryNum = (pagination.currentPage - 1) * pagination.perPage + index + 1;
                                            return (
                                                <tr key={entry['$id'] ?? entry.Entry ?? index} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-5 py-3.5 text-gray-400 text-xs font-mono">
                                                        {entryNum}
                                                    </td>
                                                    {columns.map((col) => (
                                                        <td key={col} className="px-5 py-3.5 text-gray-700 max-w-xs truncate">
                                                            {cellValue(entry[col])}
                                                        </td>
                                                    ))}
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {!error && (
                        <Pagination
                            pagination={pagination}
                            onPageChange={handlePageChange}
                            label="entries"
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
