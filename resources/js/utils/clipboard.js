export function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    // Fallback for HTTP (clipboard API requires HTTPS)
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    return Promise.resolve();
}
