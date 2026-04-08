import { useState } from 'react';
import { NOWCERTS_ENTITY_COLORS } from '@/constants/nowcerts';

function MappingSelect({ internalName, current, availableFields, onChange }) {
    const selectValue = current ? `${current.entity}.${current.field}` : '';

    function handleChange(e) {
        const val = e.target.value;
        if (!val) {
            onChange(internalName, null);
        } else {
            const [entity, ...rest] = val.split('.');
            onChange(internalName, { entity, field: rest.join('.') });
        }
    }

    return (
        <select
            value={selectValue}
            onChange={handleChange}
            className={`w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white
                focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                ${current ? NOWCERTS_ENTITY_COLORS[current.entity] ?? 'text-gray-700' : 'text-gray-400'}`}
        >
            <option value="">— unmapped —</option>
            {Object.entries(availableFields).map(([entity, fieldList]) => (
                <optgroup key={entity} label={entity}>
                    {fieldList.map(f => (
                        <option key={f} value={`${entity}.${f}`}>{f}</option>
                    ))}
                </optgroup>
            ))}
        </select>
    );
}

export default function SchemaField({ field, depth = 0, mappings, availableFields, onChange }) {
    const name         = field.Name         ?? field.name         ?? '—';
    const internalName = field.InternalName ?? field.internalName ?? name;
    const type         = field.Type         ?? field.type         ?? '—';
    const required     = field.Required     ?? field.required     ?? false;
    const children     = field.Children     ?? field.children     ?? field.Fields ?? field.fields ?? [];
    const isGroup      = type === 'discovered-group';

    const [expanded, setExpanded] = useState(false);

    const current = mappings[internalName] ?? null;

    // Collapsible group — header + inline 2-column child grid
    if (isGroup) {
        const mappedCount = children.filter(c => {
            const k = c.InternalName ?? c.internalName ?? c.Name ?? c.name;
            return !!mappings[k];
        }).length;

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
                            <table className="w-full border border-gray-100 rounded-lg overflow-hidden mt-1">
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
                                                        availableFields={availableFields}
                                                        onChange={onChange}
                                                    />
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
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

                {/* NowCerts mapping dropdown */}
                <td className="py-2 pr-5">
                    <MappingSelect
                        internalName={internalName}
                        current={current}
                        availableFields={availableFields}
                        onChange={onChange}
                    />
                </td>

            </tr>

            {children.map((child, i) => (
                <SchemaField
                    key={child.InternalName ?? child.internalName ?? i}
                    field={child}
                    depth={depth + 1}
                    mappings={mappings}
                    availableFields={availableFields}
                    onChange={onChange}
                />
            ))}
        </>
    );
}
