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

export function useImageLightbox() {
    function open(url: string, name: string = ''): void {
        state.url = url;
        state.name = name;
        state.visible = true;
    }

    function close(): void {
        state.visible = false;
        // Clear after the fade-out so the image does not stay in memory
        setTimeout(() => {
            state.url = '';
            state.name = '';
        }, 200);
    }

    return { open, close, state };
}
