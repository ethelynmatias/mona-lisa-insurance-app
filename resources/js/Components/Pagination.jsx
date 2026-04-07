export default function Pagination({ pagination, onPageChange, label = 'results' }) {
    const { currentPage, totalPages, total, perPage } = pagination;

    if (!total || totalPages <= 1) return null;

    const from  = (currentPage - 1) * perPage + 1;
    const to    = Math.min(currentPage * perPage, total);

    const pages = Array.from({ length: totalPages }, (_, i) => i + 1).filter(
        (p) => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 2
    );

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-4 border-t border-gray-100">
            <p className="text-sm text-gray-500 order-2 sm:order-1">
                Showing{' '}
                <span className="font-medium text-gray-900">{from}–{to}</span>
                {' '}of{' '}
                <span className="font-medium text-gray-900">{total}</span>
                {' '}{label}
            </p>

            <div className="flex items-center gap-1 order-1 sm:order-2">
                {/* Prev */}
                <button
                    onClick={() => onPageChange(currentPage - 1)}
                    disabled={currentPage === 1}
                    aria-label="Previous page"
                    className="p-2 rounded-lg text-gray-500 hover:bg-gray-100
                        disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                {/* Page numbers */}
                {pages.map((p, i) => {
                    const prev = pages[i - 1];
                    return (
                        <span key={p} className="flex items-center gap-1">
                            {prev && p - prev > 1 && (
                                <span className="px-1 text-gray-400 text-sm select-none">…</span>
                            )}
                            <button
                                onClick={() => onPageChange(p)}
                                aria-label={`Page ${p}`}
                                aria-current={p === currentPage ? 'page' : undefined}
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

                {/* Next */}
                <button
                    onClick={() => onPageChange(currentPage + 1)}
                    disabled={currentPage === totalPages}
                    aria-label="Next page"
                    className="p-2 rounded-lg text-gray-500 hover:bg-gray-100
                        disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                >
                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>
    );
}
