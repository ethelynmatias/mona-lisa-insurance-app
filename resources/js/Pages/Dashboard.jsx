import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

function StatusBadge({ available }) {
    return available ? (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
            <span className="w-1.5 h-1.5 rounded-full bg-green-500" />
            Active
        </span>
    ) : (
        <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
            <span className="w-1.5 h-1.5 rounded-full bg-gray-400" />
            Inactive
        </span>
    );
}

function Pagination({ pagination, onPageChange }) {
    const { currentPage, totalPages, total, perPage } = pagination;

    if (totalPages <= 1) return null;

    const from = (currentPage - 1) * perPage + 1;
    const to   = Math.min(currentPage * perPage, total);

    const pages = Array.from({ length: totalPages }, (_, i) => i + 1).filter(
        (p) => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 2
    );

    return (
        <div className="flex items-center justify-between px-5 py-4 border-t border-gray-100">
            <p className="text-sm text-gray-500">
                Showing <span className="font-medium text-gray-900">{from}–{to}</span> of{' '}
                <span className="font-medium text-gray-900">{total}</span> forms
            </p>

            <div className="flex items-center gap-1">
                <button
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                    className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {pages.map((p, i) => {
                    const prev = pages[i - 1];
                    return (
                        <span key={p} className="flex items-center gap-1">
                            {prev && p - prev > 1 && (
                                <span className="px-1 text-gray-400 text-sm">…</span>
                            )}
                            <button
                                onClick={() => onPageChange(p)}
                                className={`w-8 h-8 rounded-lg text-sm font-medium transition-colors
                                    ${p === currentPage
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100'
                                    }`}
                            >
                                {p}
                            </button>
                        </span>
                    );
                })}

                <button
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                    className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    );
}

export default function Dashboard() {
    const { forms, search: initialSearch, filter: initialFilter, pagination, error } = usePage().props;

    const [search, setSearch] = useState(initialSearch ?? '');
    const [filter, setFilter] = useState(initialFilter ?? 'all');

    const navigate = (params) => {
        router.get('/dashboard', { search, filter, page: 1, ...params }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearch = (value) => {
        setSearch(value);
        navigate({ search: value, page: 1 });
    };

    const handleFilter = (value) => {
        setFilter(value);
        navigate({ filter: value, page: 1 });
    };

    const handlePageChange = (page) => {
        navigate({ page });
    };

    return (
        <AuthenticatedLayout title="Dashboard">
            <div className="space-y-4">

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Cognito Forms</h2>
                        <p className="text-sm text-gray-500 mt-0.5">All forms from your Cognito Forms organisation</p>
                    </div>
                </div>

                {/* Card */}
                <div className="bg-white rounded-xl border border-gray-200">

                    {/* Search + filter toolbar */}
                    <div className="flex flex-col sm:flex-row gap-3 px-5 py-4 border-b border-gray-100">
                        {/* Search */}
                        <div className="relative flex-1">
                            <svg className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => handleSearch(e.target.value)}
                                placeholder="Search forms by name…"
                                className="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg outline-none
                                    focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        {/* Filter */}
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500 whitespace-nowrap">Status:</span>
                            <div className="flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                                {['all', 'active', 'inactive'].map((f) => (
                                    <button
                                        key={f}
                                        onClick={() => handleFilter(f)}
                                        className={`px-3 py-2 capitalize transition-colors
                                            ${filter === f
                                                ? 'bg-blue-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-50'
                                            }`}
                                    >
                                        {f}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Error state */}
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
                                        <th className="px-5 py-3 font-medium">Form Name</th>
                                        <th className="px-5 py-3 font-medium hidden md:table-cell">Form ID</th>
                                        <th className="px-5 py-3 font-medium hidden lg:table-cell">Entries</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                        <th className="px-5 py-3 font-medium hidden lg:table-cell">Created</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {forms.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-5 py-16 text-center">
                                                <svg className="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p className="text-gray-400 text-sm">No forms found</p>
                                                {(search || filter !== 'all') && (
                                                    <button
                                                        onClick={() => { setSearch(''); setFilter('all'); navigate({ search: '', filter: 'all', page: 1 }); }}
                                                        className="mt-2 text-sm text-blue-600 hover:underline"
                                                    >
                                                        Clear filters
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        forms.map((form) => (
                                            <tr key={form.Id ?? form.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-5 py-3.5">
                                                    <span className="font-medium text-gray-900">{form.Name ?? form.name}</span>
                                                </td>
                                                <td className="px-5 py-3.5 hidden md:table-cell font-mono text-xs text-gray-400">
                                                    {form.Id ?? form.id}
                                                </td>
                                                <td className="px-5 py-3.5 hidden lg:table-cell text-gray-600">
                                                    {(form.EntryCount ?? form.entryCount ?? 0).toLocaleString()}
                                                </td>
                                                <td className="px-5 py-3.5">
                                                    <StatusBadge available={form.IsAvailable ?? form.isAvailable ?? false} />
                                                </td>
                                                <td className="px-5 py-3.5 hidden lg:table-cell text-gray-500">
                                                    {form.Created || form.created
                                                        ? new Date(form.Created ?? form.created).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
                                                        : '—'
                                                    }
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* Pagination */}
                    {!error && pagination.total > 0 && (
                        <Pagination pagination={pagination} onPageChange={handlePageChange} />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
