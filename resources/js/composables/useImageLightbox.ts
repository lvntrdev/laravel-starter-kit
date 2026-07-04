// resources/js/composables/useImageLightbox.ts

/**
 * Global image lightbox — one overlay rendered once in AdminLayout.
 *
 * Use when you want a Google-Drive-style fullscreen preview for images.
 * For non-image files use `useDialog` + `FilePreviewModal` instead.
 *
 * @example
 *   const lightbox = useImageLightbox();
 *   lightbox.open('/storage/avatars/123.jpg', 'avatar.jpg');
 *   // gallery mode — pass the full image list + the clicked index for ←/→ navigation
 *   lightbox.open(url, name, images, index);
 *   lightbox.close();
 */

export interface LightboxItem {
    url: string;
    name: string;
}

interface LightboxState {
    visible: boolean;
    url: string;
    name: string;
    items: LightboxItem[];
    index: number;
}

const state = reactive<LightboxState>({
    visible: false,
    url: '',
    name: '',
    items: [],
    index: 0,
});

/**
 * Pending teardown timer — a rapid close → open sequence would otherwise
 * let the previous close()'s 200 ms timer wipe the newly-opened image.
 */
let closeTimer: ReturnType<typeof setTimeout> | null = null;

export function useImageLightbox() {
    function open(url: string, name: string = '', items: LightboxItem[] = [], index: number = 0): void {
        if (closeTimer !== null) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        state.url = url;
        state.name = name;
        state.items = items;
        state.index = items.length > 0 ? index : 0;
        state.visible = true;
    }

    /** Gallery navigation — no-op outside gallery mode (items.length < 2). */
    function next(): void {
        if (state.items.length < 2) return;
        state.index = (state.index + 1) % state.items.length;
        const item = state.items[state.index];
        state.url = item.url;
        state.name = item.name;
    }

    /** Gallery navigation — no-op outside gallery mode (items.length < 2). */
    function prev(): void {
        if (state.items.length < 2) return;
        state.index = (state.index - 1 + state.items.length) % state.items.length;
        const item = state.items[state.index];
        state.url = item.url;
        state.name = item.name;
    }

    function close(): void {
        state.visible = false;

        if (closeTimer !== null) {
            clearTimeout(closeTimer);
        }

        // Clear after the fade-out so the image does not stay in memory
        closeTimer = setTimeout(() => {
            state.url = '';
            state.name = '';
            state.items = [];
            state.index = 0;
            closeTimer = null;
        }, 200);
    }

    return { open, close, next, prev, state };
}
