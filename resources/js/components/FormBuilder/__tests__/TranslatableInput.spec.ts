import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import PrimeVue from 'primevue/config';
import InputText from 'primevue/inputtext';
import InputGroup from 'primevue/inputgroup';
import InputGroupAddon from 'primevue/inputgroupaddon';
import Textarea from 'primevue/textarea';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import TabPanels from 'primevue/tabpanels';
import TabPanel from 'primevue/tabpanel';

import TranslatableInput from '../inputs/TranslatableInput.vue';
import type { TranslatableTextFieldConfig } from '../core/types';

// ── Mocks ────────────────────────────────────────────────────────────────────

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
        InputGroup,
        InputGroupAddon,
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

// ── Tests: single locale ─────────────────────────────────────────────────────

describe('TranslatableInput — single locale', () => {
    it('renders without row wrappers in single-locale mode', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Test' } },
            global: globalConfig,
        });

        const rows = wrapper.findAll('.sk-translatable-field__row');
        expect(rows).toHaveLength(0);
    });

    it('renders without InputGroupAddon locale badge in single-locale mode', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Hello' } },
            global: globalConfig,
        });

        // In single mode, no InputGroupAddon; check no locale badge div wrapping
        const rows = wrapper.findAll('.sk-translatable-field__row');
        expect(rows).toHaveLength(0);
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

// ── Tests: multiple locales ───────────────────────────────────────────────────

describe('TranslatableInput — multiple locales (inline)', () => {
    it('renders two rows for two locales in default inline layout', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        const rows = wrapper.findAll('.sk-translatable-field__row');
        expect(rows).toHaveLength(2);
    });

    it('renders locale badge text TR and EN in InputGroupAddon', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        const html = wrapper.html();
        expect(html).toContain('TR');
        expect(html).toContain('EN');
    });

    it('renders two input elements for two locales', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Elma', en: 'Apple' } },
            global: globalConfig,
        });

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(2);
    });
});

// ── Tests: locale filters ─────────────────────────────────────────────────────

describe('TranslatableInput — locale filter options', () => {
    it('onlyLocales with one locale → single mode (no rows)', () => {
        const field = makeTextField({ onlyLocales: ['tr'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        const rows = wrapper.findAll('.sk-translatable-field__row');
        expect(rows).toHaveLength(0);

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(1);
    });

    it('exceptLocales removes locale leaving one → single mode', () => {
        const field = makeTextField({ exceptLocales: ['en'] });
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: '', en: '' } },
            global: globalConfig,
        });

        const rows = wrapper.findAll('.sk-translatable-field__row');
        expect(rows).toHaveLength(0);

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(1);
    });
});

// ── Tests: per-locale errors ──────────────────────────────────────────────────

describe('TranslatableInput — per-locale error display', () => {
    it('shows error message for tr locale', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: {
                field,
                modelValue: { tr: '', en: '' },
                errors: { 'name.tr': 'Bu alan zorunludur.' },
            },
            global: globalConfig,
        });

        const errors = wrapper.findAll('small.p-error');
        expect(errors.length).toBeGreaterThanOrEqual(1);

        const texts = errors.map((e) => e.text());
        expect(texts.some((t) => t.includes('Bu alan zorunludur.'))).toBe(true);
    });

    it('shows only one error when only one locale has an error', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: {
                field,
                modelValue: { tr: '', en: '' },
                errors: { 'name.tr': 'Hata!' },
            },
            global: globalConfig,
        });

        const errors = wrapper.findAll('small.p-error');
        expect(errors).toHaveLength(1);
        expect(errors[0].text()).toContain('Hata!');
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

        const errors = wrapper.findAll('small.p-error');
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

        const inputs = wrapper.findAll('input');
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

    it('initializes missing locale keys to empty string', () => {
        const field = makeTextField();
        const wrapper = mount(TranslatableInput, {
            props: { field, modelValue: { tr: 'Elma' } }, // en missing
            global: globalConfig,
        });

        const inputs = wrapper.findAll('input');
        expect(inputs).toHaveLength(2);

        // Second input (en locale) should be empty
        expect(inputs[1].element.value).toBe('');
    });
});
