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
 *   lightbox.close();
 */

interface LightboxState {
    visible: boolean;
    url: string;
    name: string;
}

const state = reactive<LightboxState>({
    visible: false,
    url: '',
    name: '',
});

/**
 * Pending teardown timer — a rapid close → open sequence would otherwise
 * let the previous close()'s 200 ms timer wipe the newly-opened image.
 */
let closeTimer: ReturnType<typeof setTimeout> | null = null;

export function useImageLightbox() {
    function open(url: string, name: string = ''): void {
        if (closeTimer !== null) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
        state.url = url;
        state.name = name;
        state.visible = true;
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
            closeTimer = null;
        }, 200);
    }

    return { open, close, state };
}
