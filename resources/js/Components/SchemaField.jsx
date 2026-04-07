const ENTITY_COLORS = {
    Insured: 'text-blue-700',
    Policy:  'text-green-700',
    Driver:  'text-orange-700',
    Vehicle: 'text-purple-700',
};

export default function SchemaField({ field, depth = 0, mappings, availableFields, onChange }) {
    const name         = field.Name         ?? field.name         ?? '—';
    const internalName = field.InternalName ?? field.internalName ?? name;
    const type         = field.Type         ?? field.type         ?? '—';
    const fieldType    = field.FieldType    ?? field.fieldType    ?? '—';
    const propertyType = field.PropertyType ?? field.propertyType ?? '—';
    const required     = field.Required     ?? field.required     ?? false;
    const children     = field.Children     ?? field.children     ?? field.Fields ?? field.fields ?? [];

    const current  = mappings[internalName] ?? null;
    // Value format: "Entity.Field" or ""
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
        <>
            <tr className="border-b border-gray-50 last:border-0 hover:bg-gray-50/40">

                {/* Field Name */}
                <td className="px-5 py-3 pr-4 w-1/5">
                    <div style={{ paddingLeft: depth * 16 }} className="flex items-center gap-2">
                        {depth > 0 && <span className="w-3 h-px bg-gray-300 flex-shrink-0" />}
                        <span className="text-sm font-medium text-gray-900">{name}</span>
                        {required && (
                            <span className="text-xs text-red-500 font-medium flex-shrink-0">required</span>
                        )}
                    </div>
                </td>

                {/* Cognito Fields */}
                <td className="py-3 pr-4">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-xs font-mono text-gray-500" title="Internal Name">
                            {internalName}
                        </span>
                        <span className="text-xs font-mono px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded" title="Type">
                            {type}
                        </span>
                        <span className="text-xs font-mono px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded" title="Field Type">
                            {fieldType}
                        </span>
                        <span className="text-xs font-mono px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded" title="Property Type">
                            {propertyType}
                        </span>
                    </div>
                </td>

                {/* NowCerts Field — dropdown */}
                <td className="py-2 pr-5">
                    <select
                        value={selectValue}
                        onChange={handleChange}
                        className={`w-full text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white
                            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                            ${current ? ENTITY_COLORS[current.entity] ?? 'text-gray-700' : 'text-gray-400'}`}
                    >
                        <option value="">— unmapped —</option>
                        {Object.entries(availableFields).map(([entity, fieldList]) => (
                            <optgroup key={entity} label={entity}>
                                {fieldList.map(f => (
                                    <option key={f} value={`${entity}.${f}`}>
                                        {f}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </select>
                </td>

            </tr>

            {children.map((child, i) => (
                <SchemaField
                    key={i}
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
