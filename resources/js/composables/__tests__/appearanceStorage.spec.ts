// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';
import { migrateLegacyAppearanceStorage } from '../useAppearanceDefaults';

// ── Legacy appearance-storage cleanup ────────────────────────────────────────
//
// Regression guard for the "accent never follows the admin global default" bug:
// the old composables eagerly persisted the hardcoded default into every user's
// localStorage, which then masked the new global default. The migration clears
// exactly those legacy defaults once, while preserving real user choices.

describe('migrateLegacyAppearanceStorage()', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('clears the legacy hardcoded defaults so the global default can seed', () => {
        window.localStorage.setItem('admin-accent-color', 'default');
        window.localStorage.setItem('admin-dark-mode', 'false');
        window.localStorage.setItem('admin-sidebar-style', 'colored');

        migrateLegacyAppearanceStorage();

        expect(window.localStorage.getItem('admin-accent-color')).toBeNull();
        expect(window.localStorage.getItem('admin-dark-mode')).toBeNull();
        expect(window.localStorage.getItem('admin-sidebar-style')).toBeNull();
        expect(window.localStorage.getItem('admin-appearance-migrated')).toBe('1');
    });

    it('preserves real (non-default) user choices', () => {
        window.localStorage.setItem('admin-accent-color', 'red');
        window.localStorage.setItem('admin-dark-mode', 'true');
        window.localStorage.setItem('admin-sidebar-style', 'light');

        migrateLegacyAppearanceStorage();

        expect(window.localStorage.getItem('admin-accent-color')).toBe('red');
        expect(window.localStorage.getItem('admin-dark-mode')).toBe('true');
        expect(window.localStorage.getItem('admin-sidebar-style')).toBe('light');
    });

    it('runs only once — a value set AFTER the migration is never touched', () => {
        window.localStorage.setItem('admin-accent-color', 'default');
        migrateLegacyAppearanceStorage();
        expect(window.localStorage.getItem('admin-accent-color')).toBeNull();

        // User later explicitly picks "default" to override a colored global —
        // the second run must NOT strip it (flag already set).
        window.localStorage.setItem('admin-accent-color', 'default');
        migrateLegacyAppearanceStorage();

        expect(window.localStorage.getItem('admin-accent-color')).toBe('default');
    });

    it('is a no-op when there is nothing to clean', () => {
        migrateLegacyAppearanceStorage();

        expect(window.localStorage.getItem('admin-appearance-migrated')).toBe('1');
        expect(window.localStorage.length).toBe(1);
    });
});
