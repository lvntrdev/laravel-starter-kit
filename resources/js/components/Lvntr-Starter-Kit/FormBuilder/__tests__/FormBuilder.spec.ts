import { describe, it, expect } from 'vitest';
import { FB } from '../core';
import type { SectionFieldConfig } from '../core/types';

// ── FormBuilder chain — grid layout, section nesting, permission gating ───────

describe('FB.form() — grid layout (cols / colSpan)', () => {
    it('.cols() sets the grid column count on the built config', () => {
        const cfg = FB.form().cols(12).build();

        expect(cfg.cols).toBe(12);
    });

    it('.colSpan() on a field carries through to the built field config', () => {
        const cfg = FB.form()
            .cols(12)
            .addFields(FB.inputText().key('title').colSpan(12), FB.inputText().key('first_name').colSpan(6))
            .build();

        expect(cfg.fields[0].colSpan).toBe(12);
        expect(cfg.fields[1].colSpan).toBe(6);
    });

    it('defaults to a 2-column vertical layout when unconfigured', () => {
        const cfg = FB.form().build();

        expect(cfg.layout).toBe('vertical');
        expect(cfg.cols).toBe(2);
    });
});

describe('FB.form() — permission gating', () => {
    it('.permission() sets the required permission key', () => {
        const cfg = FB.form().permission('users.update').build();

        expect(cfg.permission).toBe('users.update');
    });

    it('is undefined when not set (no gating by default)', () => {
        const cfg = FB.form().build();

        expect(cfg.permission).toBeUndefined();
    });
});

describe('FB.section() — nested fields', () => {
    it('addFields() nests built field configs under the section', () => {
        const cfg = FB.form()
            .addFields(
                FB.section('General').addFields(FB.inputText().key('name'), FB.inputText().key('email')),
            )
            .build();

        const section = cfg.fields[0] as SectionFieldConfig;
        expect(section.type).toBe('section');
        expect(section.title).toBe('General');
        expect(section.fields).toHaveLength(2);
        expect(section.fields[0].key).toBe('name');
        expect(section.fields[1].key).toBe('email');
    });

    it('without a title, label falls back to empty string so no title node renders', () => {
        const section = FB.section().build();

        expect(section.title).toBeUndefined();
        expect(section.label).toBe('');
    });
});
