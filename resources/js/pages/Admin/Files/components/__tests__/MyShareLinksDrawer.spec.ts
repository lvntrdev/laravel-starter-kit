import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import PrimeVue from 'primevue/config';

const listShares = vi.fn();
const revokeShare = vi.fn();

vi.mock('@/composables/useFileShare', () => ({
    useFileShare: () => ({ listShares, revokeShare }),
}));
vi.mock('@lvntr/components/utils/datetime', () => ({
    formatDateTime: (value: string) => `fmt:${value}`,
}));
vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string) => key,
}));

import MyShareLinksDrawer from '../MyShareLinksDrawer.vue';

// PrimeVue's Drawer teleports to <body>; render its content inline instead.
const DrawerStub = defineComponent({
    props: { visible: Boolean },
    setup(props, { slots }) {
        return () => (props.visible ? h('div', { class: 'drawer-stub' }, slots.default?.()) : null);
    },
});

const links = [
    { token_hash: 'aaa', created_at: '2026-10-01T10:00:00Z', expires_at: '2026-10-02T10:00:00Z' },
    { token_hash: 'bbb', created_at: '2026-10-03T10:00:00Z', expires_at: '2026-10-04T10:00:00Z' },
];

function mountDrawer() {
    return mount(MyShareLinksDrawer, {
        props: { visible: true, mediaId: 42 },
        global: { plugins: [PrimeVue], stubs: { Drawer: DrawerStub } },
    });
}

const rows = (wrapper: ReturnType<typeof mountDrawer>) => wrapper.findAll('tbody tr');

describe('MyShareLinksDrawer', () => {
    beforeEach(() => {
        listShares.mockReset();
        revokeShare.mockReset();
    });

    it('loads the links for its media on open and renders one row per link', async () => {
        listShares.mockResolvedValue(links);
        const wrapper = mountDrawer();
        await flushPromises();

        expect(listShares).toHaveBeenCalledWith(42);
        expect(rows(wrapper)).toHaveLength(2);
        expect(wrapper.text()).toContain('fmt:2026-10-01T10:00:00Z');
        expect(wrapper.text()).toContain('fmt:2026-10-04T10:00:00Z');
    });

    it('shows the empty state when the file has no active links', async () => {
        listShares.mockResolvedValue([]);
        const wrapper = mountDrawer();
        await flushPromises();

        expect(wrapper.text()).toContain('sk-file-manager.share.drawer_empty');
        expect(wrapper.find('table').exists()).toBe(false);
    });

    it('revokes a link by its token hash and removes only that row', async () => {
        listShares.mockResolvedValue(links);
        revokeShare.mockResolvedValue(true);
        const wrapper = mountDrawer();
        await flushPromises();

        await rows(wrapper)[0].find('button').trigger('click');
        await flushPromises();

        expect(revokeShare).toHaveBeenCalledWith(42, 'aaa');
        expect(rows(wrapper)).toHaveLength(1);
        expect(wrapper.text()).toContain('fmt:2026-10-03T10:00:00Z');
        expect(wrapper.text()).not.toContain('fmt:2026-10-01T10:00:00Z');
    });

    it('keeps the row when the revoke fails', async () => {
        listShares.mockResolvedValue(links);
        revokeShare.mockResolvedValue(false);
        const wrapper = mountDrawer();
        await flushPromises();

        await rows(wrapper)[0].find('button').trigger('click');
        await flushPromises();

        expect(revokeShare).toHaveBeenCalledWith(42, 'aaa');
        expect(rows(wrapper)).toHaveLength(2);
    });
});
