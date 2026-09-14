import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('laravel-vue-i18n', () => ({
    trans: (key: string) => key,
}));

import FilePreviewModal from '../FilePreviewModal.vue';

/**
 * A local file's blob: URL is same-origin with the app, so an HTML document
 * previewed from it must never run as a live document: no unsandboxed iframe
 * and no "open in new tab" affordance (a new tab has no sandbox at all).
 */
describe('FilePreviewModal — local HTML preview isolation', () => {
    it('does not render a text/html blob as a live document, and falls back to the no-preview state', () => {
        const wrapper = mount(FilePreviewModal, {
            props: {
                file: { url: 'blob:http://localhost/abc-123', name: 'evil.html', mimeType: 'text/html' },
            },
        });

        expect(wrapper.find('iframe').exists()).toBe(false);
        expect(wrapper.text()).toContain('sk-file-manager.labels.no_preview');
    });

    it('hides the "open in new tab" button for a same-origin blob HTML document', () => {
        const wrapper = mount(FilePreviewModal, {
            props: {
                file: { url: 'blob:http://localhost/abc-123', name: 'evil.html', mimeType: 'text/html' },
            },
        });

        expect(wrapper.find('[icon="pi pi-external-link"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('sk-file-manager.labels.open_in_new_tab');
    });

    it('still previews an ordinary text file in an iframe, sandboxed with no scripts and no same-origin', () => {
        const wrapper = mount(FilePreviewModal, {
            props: {
                file: { url: 'blob:http://localhost/def-456', name: 'notes.txt', mimeType: 'text/plain' },
            },
        });

        const iframe = wrapper.find('iframe');
        expect(iframe.exists()).toBe(true);
        expect(iframe.attributes('sandbox')).toBe('');
    });

    // Chromium renders a PDF through its built-in viewer, which an empty
    // `sandbox` blocks — the frame goes blank. A PDF is not the live-document
    // risk this file guards against (HTML/XML never reaches an iframe at all),
    // so the PDF branch deliberately keeps the unsandboxed frame it always had.
    it('leaves the PDF iframe unsandboxed so the browser viewer still renders it', () => {
        const wrapper = mount(FilePreviewModal, {
            props: {
                file: { url: 'blob:http://localhost/ghi-789', name: 'report.pdf', mimeType: 'application/pdf' },
            },
        });

        const iframe = wrapper.find('iframe');
        expect(iframe.exists()).toBe(true);
        expect(iframe.attributes('sandbox')).toBeUndefined();
    });

    it('never grants allow-scripts or allow-same-origin on the preview iframe', () => {
        const wrapper = mount(FilePreviewModal, {
            props: {
                file: { url: 'blob:http://localhost/def-456', name: 'notes.txt', mimeType: 'text/plain' },
            },
        });

        const sandbox = wrapper.find('iframe').attributes('sandbox') ?? '';
        expect(sandbox).not.toContain('allow-scripts');
        expect(sandbox).not.toContain('allow-same-origin');
    });
});
