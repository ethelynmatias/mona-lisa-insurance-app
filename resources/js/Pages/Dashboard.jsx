import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SearchInput from '@/Components/SearchInput';
import Pagination from '@/Components/Pagination';
import { STATUS_OPTIONS } from '@/constants/statusOptions';

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

export default function Dashboard() {
    const { forms, search: initialSearch, status: initialStatus, pagination, error } = usePage().props;

    const [search, setSearch] = useState(initialSearch ?? '');
    const [status, setStatus] = useState(initialStatus ?? 'all');

    const navigate = (params) => {
        router.get('/dashboard', { search, status, page: 1, ...params }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearch = (value) => {
        setSearch(value);
        navigate({ search: value, page: 1 });
    };

    const handleStatus = (value) => {
        setStatus(value);
        navigate({ status: value, page: 1 });
    };

    const handlePageChange = (page) => {
        navigate({ page });
    };

    const hasFilters = search || status !== 'all';

    return (
        <AuthenticatedLayout title="Dashboard">
            <div className="space-y-4">

                {/* Header */}
                <div>
                    <h2 className="text-lg font-semibold text-gray-900">Cognito Forms</h2>
                    <p className="text-sm text-gray-500 mt-0.5">All forms from your Cognito Forms organisation</p>
                </div>

                {/* Card */}
                <div className="bg-white rounded-xl border border-gray-200">

                    {/* Toolbar */}
                    <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 px-5 py-4 border-b border-gray-100">
                        <SearchInput
                            value={search}
                            onChange={handleSearch}
                            placeholder="Search forms by name…"
                            className="w-full sm:max-w-xs"
                        />

                        {/* Status filter */}
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-500 whitespace-nowrap">Status:</span>
                            <div className="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                                {STATUS_OPTIONS.map(({ value, label }) => (
                                    <button
                                        key={value}
                                        onClick={() => handleStatus(value)}
                                        className={`px-3 py-1.5 font-medium transition-colors
                                            ${status === value
                                                ? 'bg-blue-600 text-white'
                                                : 'text-gray-600 hover:bg-gray-50 bg-white'
                                            }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </div>
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
                                        <th className="px-5 py-3 font-medium">Form Name</th>
                                        <th className="px-5 py-3 font-medium hidden md:table-cell">Form ID</th>
                                        <th className="px-5 py-3 font-medium">Entries</th>
                                        <th className="px-5 py-3 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {forms.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-5 py-16 text-center">
                                                <svg className="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p className="text-gray-400 text-sm">No forms found</p>
                                                {hasFilters && (
                                                    <button
                                                        onClick={() => { setSearch(''); setStatus('all'); navigate({ search: '', status: 'all', page: 1 }); }}
                                                        className="mt-2 text-sm text-blue-600 hover:underline"
                                                    >
                                                        Clear filters
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ) : (
                                        forms.map((form) => {
                                            const entryCount = form.EntryCount
                                                ?? form.entryCount
                                                ?? form.Entries?.length
                                                ?? form.entries?.length
                                                ?? 0;

                                            return (
                                                <tr key={form.Id ?? form.id} className="hover:bg-gray-50 transition-colors">
                                                    <td className="px-5 py-3.5 font-medium text-gray-900">
                                                        {form.Name ?? form.name}
                                                    </td>
                                                    <td className="px-5 py-3.5 hidden md:table-cell font-mono text-xs text-gray-400">
                                                        {form.Id ?? form.id}
                                                    </td>
                                                    <td className="px-5 py-3.5 text-gray-600">
                                                        {Number(entryCount).toLocaleString()}
                                                    </td>
                                                    <td className="px-5 py-3.5">
                                                        <StatusBadge available={form.IsAvailable ?? form.isAvailable ?? false} />
                                                    </td>
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
                            label="forms"
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
