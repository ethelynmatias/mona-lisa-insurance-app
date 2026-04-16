export default function AutoFillInformation({ formId }) {
    const getMessage = () => {
        if (formId === '16') {
            return (
                <>
                    Vehicle information and driver details will be automatically filled on the backend based on form data patterns.
                    You only need to map specific fields if you want to override the auto-detection. If there is missing information,
                    you can find it in <b>NowCerts Notes</b>.
                </>
            );
        }
        
        // Default message for other forms
        return (
            <>
                Vehicle information and driver details will be automatically filled on the backend based on form data patterns.
                You only need to map specific fields if you want to override the auto-detection. If there are missing fields,
                you can find them on <b>NowCerts Notes</b>.
            </>
        );
    };

    return (
        <div className="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3">
            <div className="flex items-start gap-3">
                <svg className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <p className="text-sm font-medium text-blue-900">Auto-Fill Information</p>
                    <p className="text-sm text-blue-700 mt-1">
                        {getMessage()}
                    </p>
                </div>
            </div>
        </div>
    );
}