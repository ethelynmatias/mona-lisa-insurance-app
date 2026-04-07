export default function SortableHeader({ label, field, sort, direction, onSort, className = '' }) {
    const active = sort === field;
    const next   = active && direction === 'asc' ? 'desc' : 'asc';

    return (
        <th className={`px-5 py-3 font-medium ${className}`}>
            <button
                onClick={() => onSort(field, next)}
                className="inline-flex items-center gap-1 group select-none"
            >
                <span className={active ? 'text-blue-600' : 'text-gray-500'}>
                    {label}
                </span>
                <span className="flex flex-col">
                    <svg
                        className={`w-3 h-3 -mb-0.5 transition-colors ${active && direction === 'asc' ? 'text-blue-600' : 'text-gray-300 group-hover:text-gray-400'}`}
                        fill="currentColor" viewBox="0 0 24 24"
                    >
                        <path d="M12 4l8 8H4z" />
                    </svg>
                    <svg
                        className={`w-3 h-3 transition-colors ${active && direction === 'desc' ? 'text-blue-600' : 'text-gray-300 group-hover:text-gray-400'}`}
                        fill="currentColor" viewBox="0 0 24 24"
                    >
                        <path d="M12 20l-8-8h16z" />
                    </svg>
                </span>
            </button>
        </th>
    );
}
