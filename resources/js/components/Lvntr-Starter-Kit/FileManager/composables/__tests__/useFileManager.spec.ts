import { describe, it, expect, vi, beforeEach } from 'vitest';
import { flushPromises } from '@vue/test-utils';

// Every api.get() parks its resolver so the test can settle requests out of order.
const resolvers: Array<{ url: string; resolve: (value: unknown) => void }> = [];
const apiGet = vi.fn((url: string) => new Promise((resolve) => resolvers.push({ url, resolve })));

vi.mock('@/composables/useApi', () => ({
    useApi: () => ({ get: apiGet, post: vi.fn(), patch: vi.fn(), delete: vi.fn() }),
}));
vi.mock('@/composables/useCsrf', () => ({ getXsrfToken: () => 'token' }));
vi.mock('@/composables/useBasePath', () => ({ withBasePath: (p: string) => p }));
vi.mock('laravel-vue-i18n', () => ({ trans: (k: string) => k }));

const { useFileManager } = await import('../useFileManager');

function contents(fileName: string) {
    return {
        folder: null,
        folders: [],
        files: [{ id: fileName, file_name: fileName }],
        stats: { file_count: 1, total_size: 0 },
    };
}

describe('useFileManager', () => {
    beforeEach(() => {
        resolvers.length = 0;
        apiGet.mockClear();
    });

    it('discards a stale folder response that resolves after a newer one', async () => {
        const fm = useFileManager({ context: 'admin' });

        const first = fm.loadContents('a');
        const second = fm.loadContents('b');
        resolvers[1].resolve(contents('from-b'));
        await second;
        resolvers[0].resolve(contents('from-a'));
        await first;

        expect(fm.currentFolderId.value).toBe('b');
        expect(fm.contents.files[0].file_name).toBe('from-b');
        expect(fm.loading.contents).toBe(false);
    });

    it('keeps the trash view when sorting', async () => {
        const fm = useFileManager({ context: 'admin' });

        const trash = fm.loadTrash();
        resolvers[0].resolve(contents('trashed'));
        await trash;

        const sorted = fm.setSort('size', 'desc');
        expect(resolvers[1].url).toContain('/file-manager/trash/contents');
        resolvers[1].resolve(contents('trashed'));
        await sorted;

        expect(fm.currentView.value).toBe('trash');
    });

    it('sorts the destination view when a sort is picked before the navigation lands', async () => {
        const fm = useFileManager({ context: 'admin' });

        const navigation = fm.loadTrash();
        const sorted = fm.setSort('size', 'desc');
        expect(resolvers[1].url).toContain('/file-manager/trash/contents');
        expect(resolvers[1].url).toContain('sort=size');
        resolvers[0].resolve(contents('trashed'));
        resolvers[1].resolve(contents('trashed-by-size'));
        await Promise.all([navigation, sorted]);

        expect(fm.currentView.value).toBe('trash');
        expect(fm.contents.files[0].file_name).toBe('trashed-by-size');
    });

    it('opens trash in its own order when the sort is null', async () => {
        const fm = useFileManager({ context: 'admin' });
        fm.sort.value = null;

        const trash = fm.loadTrash();
        const params = new URLSearchParams(resolvers[0].url.split('?')[1]);
        resolvers[0].resolve(contents('trashed'));
        await trash;

        expect(resolvers[0].url).toContain('/file-manager/trash/contents');
        expect(params.has('sort')).toBe(false);
        expect(params.has('direction')).toBe(false);
        expect(fm.sort.value).toBeNull();
    });

    it('applies a picked sort to the trash and stays on it', async () => {
        const fm = useFileManager({ context: 'admin' });
        fm.sort.value = null;

        const trash = fm.loadTrash();
        resolvers[0].resolve(contents('trashed'));
        await trash;

        const sorted = fm.setSort('size', 'desc');
        const params = new URLSearchParams(resolvers[1].url.split('?')[1]);
        resolvers[1].resolve(contents('trashed'));
        await sorted;

        expect(resolvers[1].url).toContain('/file-manager/trash/contents');
        expect(params.get('sort')).toBe('size');
        expect(params.get('direction')).toBe('desc');
        expect(fm.currentView.value).toBe('trash');
    });

    it('sends the current sort to favorites', async () => {
        const fm = useFileManager({ context: 'admin' });
        fm.sort.value = 'date';
        fm.direction.value = 'desc';

        const favorites = fm.loadFavorites();
        const params = new URLSearchParams(resolvers[0].url.split('?')[1]);
        resolvers[0].resolve(contents('fav'));
        await favorites;

        expect(resolvers[0].url).toContain('/file-manager/favorites/contents');
        expect(params.get('sort')).toBe('date');
        expect(params.get('direction')).toBe('desc');
        expect(fm.currentView.value).toBe('favorites');
    });

    it('falls back to name order when leaving a null sort for a folder', async () => {
        const fm = useFileManager({ context: 'admin' });
        fm.sort.value = null;
        fm.direction.value = 'desc';

        const folder = fm.loadContents(null);
        const params = new URLSearchParams(resolvers[0].url.split('?')[1]);
        resolvers[0].resolve(contents('root'));
        await folder;

        expect(resolvers[0].url).toContain('/file-manager/contents');
        expect(params.get('sort')).toBe('name');
        expect(params.get('direction')).toBe('asc');
        expect(fm.sort.value).toBe('name');
    });

    it('keeps the error card of a failed upload', async () => {
        class FailingXhr {
            upload = { addEventListener: vi.fn() };
            onload: (() => void) | null = null;
            onerror: (() => void) | null = null;
            open() {}
            setRequestHeader() {}
            send() {
                queueMicrotask(() => this.onerror?.());
            }
        }
        vi.stubGlobal('XMLHttpRequest', FailingXhr);

        const fm = useFileManager({ context: 'admin' });
        const upload = fm.uploadFiles([new File(['x'], 'a.txt')]);
        await flushPromises();
        resolvers[0].resolve(contents('root'));
        const result = await upload;

        expect(result.errors).toHaveLength(1);
        expect(fm.pendingUploads.value).toHaveLength(1);
        expect(fm.pendingUploads.value[0].error).toBe('sk-file-manager.errors.network_error');

        vi.unstubAllGlobals();
    });
});
