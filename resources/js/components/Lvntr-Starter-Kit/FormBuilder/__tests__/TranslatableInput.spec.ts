import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import PrimeVue from 'primevue/config';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';

import TranslatableInput from '../inputs/TranslatableInput.vue';
import type { TranslatableTextFieldConfig } from '../core/types';

// ── Mocks ────────────────────────────────────────────────────────────────────

// jsdom has no ResizeObserver; PrimeVue TabList binds one for the ink bar.
class ResizeObserverStub {
    observe() {}
    unobserve() {}
    disconnect() {}
}
// eslint-disable-next-line @typescript-eslint/no-explicit-any
globalThis.ResizeObserver = ResizeObserverStub as any;

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({
        props: {
            availableLocales: { tr: 'Türkçe', en: 'English' },
        },
    }),
}));

// Mock EditorInput (heavy tiptap dependency)
vi.mock('../inputs/EditorInput.vue', () => ({
    default: defineComponent({
        name: 'EditorInput',
        props: ['modelValue', 'minHeight', 'toolbar', 'disabled', 'invalid'],
        emits: ['update:modelValue'],
        setup(props, { emit }) {
            return () =>
                h('div', { class: 'mock-editor-input', 'data-testid': 'editor-input' }, [
                    h('textarea', {
                        value: props.modelValue ?? '',
                        'data-testid': 'editor-textarea',
                        onInput: (e: Event) => emit('update:modelValue', (e.target as HTMLTextAreaElement).value),
                    }),
                ]);
        },
    }),
}));

// ── Global config ────────────────────────────────────────────────────────────

const globalConfig = {
    plugins: [PrimeVue],
    components: {
        InputText,
        Textarea,
        Tabs,
        TabList,
        Tab,
        TabPanels,
        TabPanel,
    },
};

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeTextField(overrides: Partial<TranslatableTextFieldConfig> = {}): TranslatableTextFieldConfig {
    return {
        type: 'translatable-text',
        key: 'name',
        label: 'Name',
        ...overrides,
    };
}

/** Inputs that are actually visible (active tab panel only). */
function visibleInputs(wrapper: ReturnType<typeof mount>) {
    return wrapper.findAll('input').filter((i) => (i.element as HTMLElement).offsetParent !== undefined);
}

// ── Tests: single locale ─────────────────────────────────────────────────────

describe('TranslatableInput — single locale', () => {
    it('renders a plain input without tabs in single-locale mode', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Test' } },
            global: globalConfig,
        });

        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
        expect(wrapper.find('.sk-translatable-field--single').exists()).toBe(true);
    });

    it('renders one input in single-locale mode', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Value' } },
            global: globalConfig,
        });

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(1);
    });
});

// ── Tests: multiple locales (tabs design) ─────────────────────────────────────

describe('TranslatableInput — multiple locales (tabs)', () => {
    it('renders the tabs design for two locales', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        expect(wrapper.find('[role="tablist"]').exists()).toBe(true);
        expect(wrapper.findAll('button[role="tab"]')).toHaveLength(2);
    });

    it('renders locale badge text TR and EN as tab headers', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        const tabTexts = wrapper.findAll('button[role="tab"]').map((t) => t.text());
        expect(tabTexts.some((t) => t.includes('TR'))).toBe(true);
        expect(tabTexts.some((t) => t.includes('EN'))).toBe(true);
    });

    it('shows the first locale panel as active initially', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Elma', en: 'Apple' } },
            global: globalConfig,
        });

        const inputs = visibleInputs(wrapper);
        expect(inputs.length).toBeGreaterThanOrEqual(1);
        expect((inputs[0].element as HTMLInputElement).value).toBe('Elma');
    });
});

// ── Tests: locale filters ─────────────────────────────────────────────────────

describe('TranslatableInput — locale filter options', () => {
    it('onlyLocales with one locale → single mode (no tabs)', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
        expect(wrapper.findAll('input')).toHaveLength(1);
    });

    it('exceptLocales removes locale leaving one → single mode', () => {
        const field = makeTextField({ exceptLocales: ['en'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        expect(wrapper.find('[role="tablist"]').exists()).toBe(false);
        expect(wrapper.findAll('input')).toHaveLength(1);
    });
});

// ── Tests: per-locale errors ──────────────────────────────────────────────────

describe('TranslatableInput — per-locale error display', () => {
    it('shows error message for the active tr locale', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: {
                field,
                modelValue: { tr: '', en: '' },
                errors: { 'name.tr': 'Bu alan zorunludur.' },
            },
            global: globalConfig,
        });

        const errors = wrapper.findAll('small.text-red-500');
        expect(errors.length).toBeGreaterThanOrEqual(1);

        const texts = errors.map((e) => e.text());
        expect(texts.some((t) => t.includes('Bu alan zorunludur.'))).toBe(true);
    });

    it('marks the erroring locale tab with a warning icon', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: {
                field,
                modelValue: { tr: '', en: '' },
                errors: { 'name.tr': 'Hata!' },
            },
            global: globalConfig,
        });

        expect(wrapper.find('button[role="tab"] .pi-exclamation-circle').exists()).toBe(true);
    });

    it('shows no errors when errors object is empty', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: {
                field,
                modelValue: { tr: 'Val', en: 'Val' },
                errors: {},
            },
            global: globalConfig,
        });

        const errors = wrapper.findAll('small.text-red-500');
        expect(errors).toHaveLength(0);
    });
});

// ── Tests: emit on update ─────────────────────────────────────────────────────

describe('TranslatableInput — emit on user input', () => {
    it('emits update event with merged payload when tr input changes', async () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: 'Apple' } },
            global: globalConfig,
        });

        const inputs = visibleInputs(wrapper);
        expect(inputs.length).toBeGreaterThanOrEqual(1);

        await inputs[0].setValue('Elma');

        const emitted = wrapper.emitted();
        const hasAnyUpdate = 'update' in emitted || 'update:modelValue' in emitted;
        expect(hasAnyUpdate).toBe(true);

        const payloads = (emitted['update'] ?? emitted['update:modelValue'] ?? []) as Array<[Record<string, string>]>;
        expect(payloads.length).toBeGreaterThanOrEqual(1);

        const last = payloads[payloads.length - 1][0];
        expect(last).toMatchObject({ tr: 'Elma', en: 'Apple' });
    });
});

// ── Tests: value normalization ────────────────────────────────────────────────

describe('TranslatableInput — initial value normalization', () => {
    it('mounts without errors when modelValue is null', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: null },
            global: globalConfig,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('mounts without errors when modelValue is undefined', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: undefined },
            global: globalConfig,
        });
        expect(wrapper.exists()).toBe(true);
    });

    it('initializes missing locale keys to empty string', async () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Elma' } }, // en missing
            global: globalConfig,
        });

        // Switch to the EN tab — its input must exist and be empty
        const tabs = wrapper.findAll('button[role="tab"]');
        await tabs[1].trigger('click');
        await nextTick();

        const inputs = wrapper.findAll('input');
        const enInput = inputs[inputs.length - 1];
        expect((enInput.element as HTMLInputElement).value).toBe('');
    });
});
