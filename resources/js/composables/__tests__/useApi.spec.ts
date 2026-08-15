// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/admin/files' }),
}));

const { useApi } = await import('../useApi');

describe('useApi — application base path', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockReset();
        fetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ success: true, status: 200, message: '', data: {} }),
        });
        vi.stubGlobal('fetch', fetchMock);
        window.history.replaceState({}, '', '/admin/admin/files');
    });

    it('prefixes every app-root-relative URL and leaves non-app URLs unchanged', async () => {
        const api = useApi({ toast: false });

        await api.get('/file-manager/tree');
        await api.get('/admin/users/1/data');
        await api.get('https://cdn.example.test/file-manager/tree');
        await api.get('//cdn.example.test/file-manager/tree');
        await api.get('file-manager/tree');

        expect(fetchMock.mock.calls.map(([url]) => url)).toEqual([
            '/admin/file-manager/tree',
            '/admin/admin/users/1/data',
            'https://cdn.example.test/file-manager/tree',
            '//cdn.example.test/file-manager/tree',
            'file-manager/tree',
        ]);
    });
});
