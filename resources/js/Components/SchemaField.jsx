export default function SchemaField({ field, depth = 0 }) {
    const name         = field.Name         ?? field.name         ?? '—';
    const internalName = field.InternalName ?? field.internalName ?? '—';
    const type         = field.Type         ?? field.type         ?? '—';
    const fieldType    = field.FieldType    ?? field.fieldType    ?? '—';
    const propertyType = field.PropertyType ?? field.propertyType ?? '—';
    const required     = field.Required     ?? field.required     ?? false;
    const children     = field.Children     ?? field.children     ?? field.Fields ?? field.fields ?? [];

    return (
        <div className={depth > 0 ? 'ml-5 border-l-2 border-gray-100 pl-4' : ''}>
            <div className="grid grid-cols-[1fr_auto_auto_auto_auto] items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">

                {/* Name */}
                <div className="min-w-0">
                    <span className="text-sm font-medium text-gray-900">{name}</span>
                    {required && (
                        <span className="ml-2 text-xs text-red-500 font-medium">required</span>
                    )}
                </div>

                {/* Internal Name */}
                <span className="text-xs font-mono text-gray-500 hidden sm:block" title="Internal Name">
                    {internalName}
                </span>

                {/* Type */}
                <span className="text-xs font-mono px-2 py-0.5 bg-gray-100 text-gray-600 rounded" title="Type">
                    {type}
                </span>

                {/* Field Type */}
                <span className="text-xs font-mono px-2 py-0.5 bg-blue-50 text-blue-600 rounded hidden md:block" title="Field Type">
                    {fieldType}
                </span>

                {/* Property Type */}
                <span className="text-xs font-mono px-2 py-0.5 bg-purple-50 text-purple-600 rounded hidden md:block" title="Property Type">
                    {propertyType}
                </span>

            </div>
            {children.length > 0 && children.map((child, i) => (
                <SchemaField key={i} field={child} depth={depth + 1} />
            ))}
        </div>
    );
}
