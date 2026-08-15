const HTML_ENTITIES: Record<string, string> = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
};

export function escapeHtml(value: unknown): string {
    let text = '';

    if (value !== null && value !== undefined) {
        try {
            text = String(value);
        } catch {
            return '';
        }
    }

    return text.replace(/[&<>"']/g, (character) => HTML_ENTITIES[character]);
}
