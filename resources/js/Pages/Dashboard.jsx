import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SearchInput from '@/Components/SearchInput';
import Pagination from '@/Components/Pagination';
import SortableHeader from '@/Components/SortableHeader';

export default function Dashboard() {
    const { forms, search: initialSearch, sort: initialSort, direction: initialDirection, pagination, perPageOptions = [20, 50, 100], error } = usePage().props;

    const [search,    setSearch]    = useState(initialSearch             ?? '');
    const [sort,      setSort]      = useState(initialSort               ?? '');
    const [direction, setDirection] = useState(initialDirection          ?? 'asc');
    const [perPage,   setPerPage]   = useState(pagination?.perPage ?? perPageOptions[0]);

    const navigate = (params) => {
        router.get('/dashboard', { search, sort, direction, page: 1, per_page: perPage, ...params }, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearch = (value) => {
        setSearch(value);
        navigate({ search: value, page: 1 });
    };

    const handleSort = (field, dir) => {
        setSort(field);
        setDirection(dir);
        navigate({ sort: field, direction: dir, page: 1 });
    };

    const handlePageChange = (page) => {
        navigate({ page });
    };

    const handlePerPageChange = (value) => {
        const val = Number(value);
        setPerPage(val);
        navigate({ per_page: val, page: 1 });
    };

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
                    <div className="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                        <SearchInput
                            value={search}
                            onChange={handleSearch}
                            placeholder="Search forms by name…"
                            className="w-full sm:max-w-xs"
                        />
                        <div className="flex items-center gap-2 sm:ml-auto">
                            <label className="text-xs text-gray-500 whitespace-nowrap">Show</label>
                            <select
                                value={perPage}
                                onChange={e => handlePerPageChange(e.target.value)}
                                className="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-gray-700
                                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                {perPageOptions.map(n => (
                                    <option key={n} value={n}>{n} per page</option>
                                ))}
                            </select>
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
                                    <tr className="text-left text-xs border-b border-gray-100 bg-gray-50">
                                        <SortableHeader
                                            label="Form Name"
                                            field="Name"
                                            sort={sort}
                                            direction={direction}
                                            onSort={handleSort}
                                        />
                                        <SortableHeader
                                            label="Form ID"
                                            field="Id"
                                            sort={sort}
                                            direction={direction}
                                            onSort={handleSort}
                                            className="hidden md:table-cell"
                                        />
                                        <th className="px-5 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-50">
                                    {forms.length === 0 ? (
                                        <tr>
                                            <td colSpan={3} className="px-5 py-16 text-center">
                                                <svg className="mx-auto w-10 h-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <p className="text-gray-400 text-sm">No forms found</p>
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
                                        forms.map((form) => (
                                            <tr key={form.Id ?? form.id} className="hover:bg-gray-50 transition-colors">
                                                <td className="px-5 py-3.5 font-medium text-gray-900">
                                                    {form.Name ?? form.name}
                                                </td>
                                                <td className="px-5 py-3.5 hidden md:table-cell font-mono text-xs text-gray-400">
                                                    {form.Id ?? form.id}
                                                </td>
                                                <td className="px-5 py-3.5 text-right">
                                                    <Link
                                                        href={`/dashboard/forms/${form.Id ?? form.id}`}
                                                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                                                    >
                                                        View Details
                                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                        </svg>
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))
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
