/** One named snapshot of a table's search, sort, page size, filters and columns. */
export interface SavedView {
    name: string;
    search: string;
    sortKey: string;
    sortOrder: 'asc' | 'desc';
    perPage: number;
    /** Filter values as stored — daterange entries are ISO strings. */
    filters: Record<string, unknown>;
    order: string[];
    hidden: string[];
}

/**
 * Views are kept per table route in localStorage, so they belong to the browser,
 * not the account.
 * ponytail: browser-local; move to a user-scoped table once views must follow a user across devices.
 */
export function viewsStorageKey(route: string): string {
    return `dt:views:${route}`;
}

export function loadViews(route: string): SavedView[] {
    try {
        const raw = localStorage.getItem(viewsStorageKey(route));
        const parsed: unknown = raw ? JSON.parse(raw) : [];

        return Array.isArray(parsed)
            ? parsed.filter((v): v is SavedView => typeof v?.name === 'string' && Array.isArray(v?.order))
            : [];
    } catch {
        return [];
    }
}

export function storeViews(route: string, views: SavedView[]): void {
    try {
        localStorage.setItem(viewsStorageKey(route), JSON.stringify(views));
    } catch {
        // localStorage unavailable — the views live only until the page is left
    }
}

/** Add `view`, replacing any view with the same name (trimmed, case-insensitive). */
export function upsertView(views: SavedView[], view: SavedView): SavedView[] {
    const key = view.name.trim().toLowerCase();

    return [...views.filter((v) => v.name.trim().toLowerCase() !== key), view];
}
