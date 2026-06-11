import { describe, it, expect } from 'vitest';
import { FB } from '../core';
import type { ToggleSwitchFieldConfig } from '../core/types';

// ── ToggleSwitch builder — rich row API (icon + description) ──────────────────

describe('FB.toggleSwitch() — rich row API', () => {
    it('default build → no rowIcon / description (backwards-compatible plain render)', () => {
        const cfg = FB.toggleSwitch().key('is_active').build() as ToggleSwitchFieldConfig;

        expect(cfg.type).toBe('toggle-switch');
        expect(cfg.key).toBe('is_active');
        expect(cfg.rowIcon).toBeUndefined();
        expect(cfg.description).toBeUndefined();
    });

    it('.icon() sets rowIcon (not the base input `icon`) for the rich row layout', () => {
        const cfg = FB.toggleSwitch()
            .key('login_throttle')
            .icon('pi pi-shield')
            .build() as ToggleSwitchFieldConfig;

        expect(cfg.rowIcon).toBe('pi pi-shield');
        // base input-icon field must stay clear so SkFormInput never treats it as an input icon
        expect((cfg as { icon?: string }).icon).toBeUndefined();
    });

    it('.description() sets description (translation key)', () => {
        const cfg = FB.toggleSwitch()
            .key('login_throttle')
            .description('sk-setting.login_throttle_desc')
            .build() as ToggleSwitchFieldConfig;

        expect(cfg.description).toBe('sk-setting.login_throttle_desc');
    });

    it('.icon() + .description() chain together', () => {
        const cfg = FB.toggleSwitch()
            .key('email_verification')
            .label('sk-setting.email_verification')
            .icon('pi pi-envelope')
            .description('sk-setting.email_verification_desc')
            .build() as ToggleSwitchFieldConfig;

        expect(cfg.rowIcon).toBe('pi pi-envelope');
        expect(cfg.description).toBe('sk-setting.email_verification_desc');
        expect(cfg.label).toBe('sk-setting.email_verification');
    });

    it('accepts a text glyph as the icon descriptor (password-policy rules)', () => {
        const cfg = FB.toggleSwitch()
            .key('password_require_mixed_case')
            .icon('Aa')
            .description('sk-setting.password_require_mixed_case_desc')
            .build() as ToggleSwitchFieldConfig;

        expect(cfg.rowIcon).toBe('Aa');
        expect(cfg.description).toBe('sk-setting.password_require_mixed_case_desc');
    });

    it('rich-row methods are chainable with the base builder API (optional, default, etc.)', () => {
        const cfg = FB.toggleSwitch()
            .key('registration')
            .icon('pi pi-user-plus')
            .description('sk-setting.registration_desc')
            .optional()
            .default(true)
            .build() as ToggleSwitchFieldConfig;

        expect(cfg.rowIcon).toBe('pi pi-user-plus');
        expect(cfg.required).toBe(false);
        expect(cfg.defaultValue).toBe(true);
    });
});
