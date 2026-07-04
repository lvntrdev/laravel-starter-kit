import { describe, it, expect } from 'vitest';
import { TB } from '../core';

// ── TabBuilder chain — tab item permission gating ─────────────────────────────

describe('TB.item() — permission', () => {
    it('a single permission is stored as a plain string', () => {
        const tab = TB.item().key('security').permission('users.update').build();

        expect(tab.permission).toBe('users.update');
    });

    it('multiple permissions are stored as an array', () => {
        const tab = TB.item().key('security').permission('users.update', 'users.delete').build();

        expect(tab.permission).toEqual(['users.update', 'users.delete']);
    });

    it('is undefined when not set (no gating by default)', () => {
        const tab = TB.item().key('general').build();

        expect(tab.permission).toBeUndefined();
    });

    it('label falls back to the key when not set', () => {
        const tab = TB.item().key('general').build();

        expect(tab.label).toBe('general');
    });

    it('throws without a key', () => {
        expect(() => TB.item().label('No key').build()).toThrow('Tab item must have a key');
    });
});

describe('TB.tabs() — requires at least one tab', () => {
    it('throws when built with no tabs', () => {
        expect(() => TB.tabs().build()).toThrow('TabBuilder must have at least one tab');
    });

    it('addTabs() carries built tab configs (incl. permission) through', () => {
        const cfg = TB.tabs().addTabs(TB.item().key('security').permission('users.update')).build();

        expect(cfg.tabs).toHaveLength(1);
        expect(cfg.tabs[0].permission).toBe('users.update');
    });
});
