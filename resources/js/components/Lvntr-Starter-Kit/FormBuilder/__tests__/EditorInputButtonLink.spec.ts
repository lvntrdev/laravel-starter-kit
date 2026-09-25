import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import PrimeVue from 'primevue/config';
import EditorInput from '../inputs/EditorInput.vue';

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: vi.fn() }) }));

/**
 * Button links ride on the link mark: a plain link must survive untouched
 * next to them, and a button keeps its variant and color through a round-trip.
 */
async function roundTrip(html: string): Promise<string> {
    const wrapper = mount(EditorInput, {
        props: { modelValue: '<p>start</p>', toolbar: 'standard', links: true },
        global: { plugins: [PrimeVue], mocks: { $t: (key: string) => key } },
    });
    await flushPromises();
    await wrapper.get('[aria-label="sk-editor.source"]').trigger('click');
    await wrapper.get('textarea').setValue(html);
    const events = wrapper.emitted('update:modelValue') ?? [];
    return events[events.length - 1]?.[0] as string;
}

describe('EditorInput button links', () => {
    it('keeps a plain link a plain link', async () => {
        const out = await roundTrip('<p><a href="https://example.test">plain</a></p>');

        expect(out).toContain('href="https://example.test"');
        expect(out).not.toContain('data-sk-button');
    });

    it('keeps a button variant and color, and derives the web css variables', async () => {
        const out = await roundTrip(
            '<p><a href="https://example.test" data-sk-button="outline" data-sk-color="#fde047">go</a></p>',
        );

        expect(out).toContain('data-sk-button="outline"');
        expect(out).toContain('data-sk-color="#fde047"');
        expect(out).toContain('--sk-button-color: #fde047; --sk-button-text: #111827');
    });

    it('drops an unknown variant or color but keeps the link', async () => {
        const out = await roundTrip(
            '<p><a href="https://example.test" data-sk-button="huge" data-sk-color="red">go</a></p>',
        );

        expect(out).toContain('href="https://example.test"');
        expect(out).not.toContain('data-sk-');
    });
});
