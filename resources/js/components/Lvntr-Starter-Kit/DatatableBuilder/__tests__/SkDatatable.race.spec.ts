import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { DB } from '../core';

// ── Controlled fetch queue ────────────────────────────────────────────────────
// Every api.get() call parks its resolver here so the test can settle requests
// OUT OF ORDER — the whole point of the sequence-token guard in fetchData().
const resolvers: Array<(value: unknown) => void> = [];
const apiGet = vi.fn(() => new Promise((resolve) => resolvers.push(resolve)));

vi.mock('@/composables/useApi', () => ({
    useApi: () => ({ get: apiGet, post: vi.fn(), put: vi.fn(), delete: vi.fn() }),
}));
vi.mock('@/composables/useDefinition', () => ({
    useDefinition: () => ({ load: vi.fn(), options: () => [], find: () => undefined }),
}));
vi.mock('@/composables/useRefreshBus', () => ({
    useRefreshBus: () => ({ on: vi.fn(), off: vi.fn(), emit: vi.fn() }),
}));

// Imported AFTER the mocks so the component picks up the mocked composables.
const { default: SkDatatable } = await import('../SkDatatable.vue');

function page(tag: string) {
    return { data: [tag], total: 1, per_page: 10, current_page: 1, last_page: 1, from: 1, to: 1 };
}

function mountTable() {
    return shallowMount(SkDatatable, {
        props: { config: DB.table().route('/api/race/dt').build() },
        // shallowMount stubs SkCard, so its slotted table template (Popover,
        // Button, InputText, …) never renders — only the component's own
        // script/lifecycle runs, which is all the race guard needs.
        global: { mocks: { $t: (k: string) => k } },
    });
}

describe('SkDatatable — out-of-order fetch guard', () => {
    beforeEach(() => {
        resolvers.length = 0;
        apiGet.mockClear();
        sessionStorage.clear();
        localStorage.clear();
    });

    afterEach(() => {
        resolvers.length = 0;
    });

    it('discards a stale response that resolves after a newer one', async () => {
        const wrapper = mountTable();

        // onMounted fires the initial fetch.
        await flushPromises();
        expect(resolvers).toHaveLength(1);
        resolvers[0](page('init'));
        await flushPromises();
        expect(wrapper.vm.pageData).toEqual(['init']);

        // Two rapid refetches (e.g. two quick filter changes) — both hit the
        // network before either resolves.
        wrapper.vm.refresh();
        wrapper.vm.refresh();
        expect(resolvers).toHaveLength(3); // index 1 = older, index 2 = newer

        // The NEWER request wins first…
        resolvers[2](page('new'));
        await flushPromises();
        expect(wrapper.vm.pageData).toEqual(['new']);

        // …then the OLDER request resolves late — it must NOT clobber the newer data.
        resolvers[1](page('stale'));
        await flushPromises();
        expect(wrapper.vm.pageData).toEqual(['new']);
    });
});
