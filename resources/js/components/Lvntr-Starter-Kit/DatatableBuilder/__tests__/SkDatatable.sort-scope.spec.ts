import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { DB } from '../core';

// ── Captured requests ─────────────────────────────────────────────────────────
// `sort` is a PAGE-GLOBAL query parameter, so on a page hosting several tables the
// one that mounts second used to read the first one's sort and ask its own endpoint
// for a column that endpoint never allowed — Spatie\QueryBuilder answers HTTP 400
// (InvalidSortQuery) and the table renders empty. What matters here is the URL the
// component actually requests.
const requested: string[] = [];
const apiGet = vi.fn((url: string) => {
    requested.push(url);

    return Promise.resolve({
        data: [],
        total: 0,
        per_page: 10,
        current_page: 1,
        last_page: 1,
        from: 0,
        to: 0,
    });
});

vi.mock('@/composables/useApi', () => ({
    useApi: () => ({ get: apiGet, post: vi.fn(), put: vi.fn(), delete: vi.fn() }),
}));
vi.mock('@/composables/useDefinition', () => ({
    useDefinition: () => ({ load: vi.fn(), options: () => [], find: () => undefined }),
}));
vi.mock('@/composables/useRefreshBus', () => ({
    useRefreshBus: () => ({ on: vi.fn(), off: vi.fn(), emit: vi.fn() }),
}));

const { default: SkDatatable } = await import('../SkDatatable.vue');

/** A table that sorts on `title` and knows nothing about `session_date`. */
function mountTable() {
    const config = DB.table()
        .route('/api/contents/dt-api')
        .addColumns(DB.column().key('title').label('Title'), DB.column().key('sort_order').label('Order'))
        .build();

    return shallowMount(SkDatatable, {
        props: { config },
        global: { mocks: { $t: (k: string) => k } },
    });
}

function setUrl(query: string): void {
    window.history.replaceState({}, '', query ? `/panel/applications/1?${query}` : '/panel/applications/1');
}

describe('SkDatatable — sort keys are scoped to the table that owns them', () => {
    beforeEach(() => {
        requested.length = 0;
        apiGet.mockClear();
        sessionStorage.clear();
        localStorage.clear();
        setUrl('');
    });

    it('ignores a neighbouring table\'s sort left in the page URL', async () => {
        setUrl('sort=session_date&page=3');

        mountTable();
        await flushPromises();

        // Neither the foreign sort nor the page number that travelled with it: the URL
        // belongs to another table, so none of it is read.
        expect(requested[0]).not.toContain('sort=');
        expect(requested[0]).toContain('page=1');
    });

    it('still restores a sort the table does own', async () => {
        setUrl('sort=-title');

        mountTable();
        await flushPromises();

        expect(requested[0]).toContain('sort=-title');
    });

    it('restores a sort on a server-published column the local config never declares', async () => {
        // A hidden-by-default column the backend publishes (`updated_at` on the Users
        // table, for instance): the server meta arrives with the first response, AFTER
        // restore, so the only proof the key is this table's own is the route-scoped
        // column blob the user's earlier visit left behind.
        localStorage.setItem(
            'dt:cols:/api/contents/dt-api',
            JSON.stringify({ order: ['title', 'sort_order', 'updated_at'], hidden: [] }),
        );
        setUrl('sort=-updated_at');

        mountTable();
        await flushPromises();

        expect(requested[0]).toContain('sort=-updated_at');
    });

    it('drops a stale sort key left in its own session blob', async () => {
        // Same route, so this really is this table's own blob — written before the
        // column was renamed or dropped. The backend rejects it exactly the same way.
        sessionStorage.setItem(
            'dt:/api/contents/dt-api',
            JSON.stringify({ search: '', sortKey: 'session_date', sortOrder: 'asc', page: 1, perPage: 10, filters: {} }),
        );
        sessionStorage.setItem('dt:/api/contents/dt-api:reload', '1');

        mountTable();
        await flushPromises();

        expect(requested[0]).not.toContain('sort=');
    });
});
