import { describe, it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import PrimeVue from 'primevue/config';
import EditorInput from '../inputs/EditorInput.vue';

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: vi.fn() }) }));

/**
 * HTML source view: whatever is typed into the textarea must reach v-model
 * immediately, so submitting the form with the source view still open never
 * loses the edit — and markup the editor cannot store is announced, not
 * dropped silently.
 */
async function openSource() {
    const wrapper = mount(EditorInput, {
        props: { modelValue: '<p>start</p>', toolbar: 'standard' },
        global: { plugins: [PrimeVue], mocks: { $t: (key: string) => key } },
    });
    await flushPromises();
    await wrapper.get('[aria-label="sk-editor.source"]').trigger('click');
    return wrapper;
}

function lastEmitted(wrapper: Awaited<ReturnType<typeof openSource>>): string {
    const events = wrapper.emitted('update:modelValue') ?? [];
    return events[events.length - 1]?.[0] as string;
}

describe('EditorInput HTML source view', () => {
    it('shows the current content as HTML', async () => {
        const wrapper = await openSource();
        expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toContain('<p>start</p>');
    });

    it('emits every edit synchronously, without leaving the source view', async () => {
        const wrapper = await openSource();
        await wrapper.get('textarea').setValue('<h2>Title</h2><p>Long <strong>work</strong></p>');

        expect(lastEmitted(wrapper)).toBe('<h2>Title</h2><p>Long <strong>work</strong></p>');
        expect(wrapper.find('textarea').exists()).toBe(true);
    });

    it('lists tags the editor will not keep, and keeps their text', async () => {
        const wrapper = await openSource();
        await wrapper.get('textarea').setValue('<p><b>bold</b> <font color="red">kept text</font></p>');

        expect(lastEmitted(wrapper)).toContain('kept text');
        const warning = wrapper.get('[role="status"]');
        expect(warning.text()).toContain('sk-editor.source_dropped');
        // <b> becomes <strong>: an alias, not a loss.
        expect(wrapper.vm.$.setupState.droppedTags).toEqual(['font']);
    });

    it('replaces the source buffer when the bound value changes from outside', async () => {
        const wrapper = await openSource();
        const emittedBefore = wrapper.emitted('update:modelValue')?.length ?? 0;
        // TranslatableInput switching locale reuses the same instance.
        await wrapper.setProps({ modelValue: '<p>other locale</p>' });

        expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('<p>other locale</p>');
        expect(wrapper.emitted('update:modelValue')?.length ?? 0).toBe(emittedBefore);

        await wrapper.get('textarea').setValue('<p>other locale edited</p>');
        expect(lastEmitted(wrapper)).toBe('<p>other locale edited</p>');
    });

    it('disables the source textarea with the field', async () => {
        const wrapper = await openSource();
        await wrapper.setProps({ disabled: true });

        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
    });
});
