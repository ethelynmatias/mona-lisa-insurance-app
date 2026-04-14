import { useState, useEffect, useRef } from 'react';
import { NOWCERTS_ENTITY_COLORS } from '@/constants/nowcerts';

function MappingSelect({ internalName, current, availableFields, onChange }) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const dropdownRef = useRef(null);
    const searchInputRef = useRef(null);

    const selectValue = current ? `${current.entity}.${current.field}` : '';
    const displayValue = current ? `${current.entity}.${current.field}` : '— unmapped —';

    // Flatten all options for searching
    const allOptions = Object.entries(availableFields).flatMap(([entity, fieldList]) =>
        fieldList.map(field => ({
            entity,
            field,
            value: `${entity}.${field}`,
            label: `${entity}.${field}`
        }))
    );

    // Filter options based on search term
    const filteredOptions = searchTerm
        ? allOptions.filter(option =>
            option.label.toLowerCase().includes(searchTerm.toLowerCase()) ||
            option.entity.toLowerCase().includes(searchTerm.toLowerCase()) ||
            option.field.toLowerCase().includes(searchTerm.toLowerCase())
        )
        : allOptions;

    function handleSelect(value) {
        if (!value) {
            onChange(internalName, null);
        } else {
            const [entity, ...rest] = value.split('.');
            onChange(internalName, { entity, field: rest.join('.') });
        }
        setIsOpen(false);
        setSearchTerm('');
    }

    function handleKeyDown(e) {
        if (e.key === 'Escape') {
            setIsOpen(false);
            setSearchTerm('');
        }
    }

    // Close dropdown when clicking outside
    useEffect(() => {
        function handleClickOutside(event) {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
                setSearchTerm('');
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    // Focus search input when dropdown opens
    useEffect(() => {
        if (isOpen && searchInputRef.current) {
            searchInputRef.current.focus();
        }
    }, [isOpen]);

    return (
        <div ref={dropdownRef} className="relative">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className={`w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-left
                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                    ${current ? NOWCERTS_ENTITY_COLORS[current.entity] ?? 'text-gray-700' : 'text-gray-400'}`}
            >
                <div className="flex items-center justify-between">
                    <span className="truncate">{displayValue}</span>
                    <svg className={`w-4 h-4 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </button>

            {isOpen && (
                <>
                    {/* Backdrop to prevent interaction with other elements */}
                    <div
                        className="fixed inset-0 z-[9998]"
                        onClick={() => {
                            setIsOpen(false);
                            setSearchTerm('');
                        }}
                    />

                    {/* Dropdown with very high z-index */}
                    <div className="absolute z-[9999] w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-64 overflow-hidden">
                        {/* Search input */}
                        <div className="p-2 border-b border-gray-100">
                            <input
                                ref={searchInputRef}
                                type="text"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                onKeyDown={handleKeyDown}
                                placeholder="Search fields..."
                                className="w-full text-xs px-2 py-1 border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-blue-500 min-w-0"
                            />
                        </div>

                        {/* Options list */}
                        <div className="max-h-48 overflow-y-auto overflow-x-hidden">
                            <button
                                type="button"
                                onClick={() => handleSelect('')}
                                className="w-full text-left px-2.5 py-1.5 text-xs text-gray-400 hover:bg-gray-50 border-b border-gray-50 truncate"
                            >
                                — unmapped —
                            </button>

                            {filteredOptions.length === 0 ? (
                                <div className="px-2.5 py-1.5 text-xs text-gray-400 truncate">No fields found</div>
                            ) : (
                                filteredOptions.map(option => (
                                    <button
                                        key={option.value}
                                        type="button"
                                        onClick={() => handleSelect(option.value)}
                                        className={`w-full text-left px-2.5 py-1.5 text-xs hover:bg-gray-50 border-b border-gray-50 last:border-b-0 truncate
                                            ${NOWCERTS_ENTITY_COLORS[option.entity] ?? 'text-gray-700'}`}
                                        title={`${option.entity}.${option.field}`}
                                    >
                                        <span className="font-medium">{option.entity}</span>
                                        <span className="text-gray-500"> • </span>
                                        <span>{option.field}</span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}


export default function SchemaField({ field, formId, depth = 0, mappings, availableFields, onChange }) {
    // Merge all available fields (contacts + properties)
    const allAvailableFields = availableFields;
    const name         = field.Name         ?? field.name         ?? '—';
    const internalName = field.InternalName ?? field.internalName ?? name;
    const type         = field.Type         ?? field.type         ?? '—';
    const required     = field.Required     ?? field.required     ?? false;
    const children     = field.Children     ?? field.children     ?? field.Fields ?? field.fields ?? [];
    const isGroup      = type === 'discovered-group';

    // Form 13 specific: Hide Name2 and Entry collapsible groups
    if (formId === '13' && (name === 'Name2' || name === 'Entry')) {
        return null;
    }

    // Form 11 specific: Hide Entry collapsible group
    if (formId === '11' && name === 'Entry') {
        return null;
    }

    // Hide Form collapsible for all forms
    if (name === 'Form') {
        return null;
    }

    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        if (field._searchExpanded) setExpanded(true);
    }, [field._searchExpanded]);

    const current = mappings[internalName] ?? null;

    // Collapsible group — header + inline 2-column child grid
    if (isGroup) {
        const mappedCount = children.filter(c => {
            const k = c.InternalName ?? c.internalName ?? c.Name ?? c.name;
            return !!mappings[k];
        }).length;
        if (name === 'Form') {
            return null;
        }

        return (
            <>
                {/* Group header — clickable, collapses children */}
                <tr
                    className="border-b border-gray-100 cursor-pointer select-none hover:bg-gray-50/60 transition-colors"
                    onClick={() => setExpanded(v => !v)}
                >
                    <td colSpan={2} className="px-5 py-3">
                        <div className="flex items-center gap-2">
                            <svg
                                className={`w-3.5 h-3.5 text-gray-400 flex-shrink-0 transition-transform duration-150
                                    ${expanded ? 'rotate-90' : ''}`}
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                            </svg>
                            <span className="text-sm font-semibold text-gray-800">{name}</span>
                            <span className="ml-auto text-xs text-gray-400 tabular-nums">
                                {mappedCount} / {children.length} mapped
                            </span>
                        </div>
                    </td>
                </tr>

                {/* Child rows — label + dropdown only */}
                {expanded && (
                    <tr>
                        <td colSpan={2} className="px-5 pb-4 pt-0">
                            <div className="overflow-visible">
                                <table className="w-full border border-gray-100 rounded-lg overflow-visible mt-1">
                                    <tbody>
                                    {children.map((child) => {
                                        const childName    = child.Name ?? child.name ?? '—';
                                        const childKey     = child.InternalName ?? child.internalName ?? childName;
                                        const childCurrent = mappings[childKey] ?? null;
                                        return (
                                            <tr key={childKey} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/40">
                                                <td className="pl-4 pr-3 py-2.5 w-56 max-w-56">
                                                    <span className="text-sm text-gray-700 break-words">{childName}</span>
                                                </td>
                                                <td className="pr-4 py-2">
                                                    <MappingSelect
                                                        internalName={childKey}
                                                        current={childCurrent}
                                                        availableFields={allAvailableFields}
                                                        onChange={onChange}
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                )}
            </>
        );
    }
    // Standard field row
    return (
        <>
            <tr className={`border-b border-gray-50 last:border-0 hover:bg-gray-50/40
                ${depth > 0 ? 'bg-white' : ''}`}>

                {/* Field Name */}
                <td className="px-5 py-3 pr-4 w-64 max-w-64">
                    <div style={{ paddingLeft: depth * 20 }} className="flex items-center gap-2 min-w-0">
                        {depth > 0 && (
                            <span className="w-4 h-px bg-amber-200 flex-shrink-0" />
                        )}
                        <span
                            className={`text-sm font-medium truncate ${depth > 0 ? 'text-gray-700' : 'text-gray-900'}`}
                            title={name}
                        >
                            {name}
                        </span>
                        {required && (
                            <span className="text-xs text-red-500 font-medium flex-shrink-0">required</span>
                        )}
                    </div>
                </td>

                {/* NowCerts mapping */}
                <td className="py-2 pr-5">
                    <MappingSelect
                        internalName={internalName}
                        current={current}
                        availableFields={allAvailableFields}
                        onChange={onChange}
                    />
                </td>

            </tr>

            {children.map((child, i) => (
                <SchemaField
                    key={child.InternalName ?? child.internalName ?? i}
                    field={child}
                    formId={formId}
                    depth={depth + 1}
                    mappings={mappings}
                    availableFields={availableFields}
                    onChange={onChange}
                />
            ))}
        </>
    );
}
