import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mocks must be declared before any import that transitively loads the modules.

// Mock @inertiajs/vue3 — useTheme calls usePage() to seed currentTheme from SSR.
vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(),
}));

// Mock @primevue/themes — only replace usePreset (which injects a <style>);
// keep definePreset real so presets/default.ts and presets/corporate.ts can
// call it at import time without exploding.
vi.mock('@primevue/themes', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@primevue/themes')>();
    return {
        ...actual,
        usePreset: vi.fn(),
    };
});

import { usePage } from '@inertiajs/vue3';
import { usePreset } from '@primevue/themes';
import { useTheme } from '../useTheme';
// @/theme/presets resolves to stubs/resources/js/theme/presets/index.ts
import { presets, DEFAULT_THEME } from '@/theme/presets';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Wire usePage mock to return a given theme prop (or undefined). */
function mockPageTheme(theme: string | undefined): void {
    vi.mocked(usePage).mockReturnValue({ props: { theme } } as ReturnType<typeof usePage>);
}

beforeEach(() => {
    vi.clearAllMocks();
});

// ── Tests: setTheme → usePreset dispatch ─────────────────────────────────────

describe('useTheme — setTheme resolves preset and calls usePreset', () => {
    it('setTheme("default") calls usePreset with the default preset object', () => {
        mockPageTheme('default');

        const { setTheme } = useTheme();
        const resolved = setTheme('default');

        expect(resolved).toBe('default');
        expect(vi.mocked(usePreset)).toHaveBeenCalledWith(presets.default);
        expect(vi.mocked(usePreset)).toHaveBeenCalledTimes(1);
    });

    it('setTheme("corporate") calls usePreset with the corporate preset object', () => {
        mockPageTheme('default');

        const { setTheme } = useTheme();
        const resolved = setTheme('corporate');

        expect(resolved).toBe('corporate');
        expect(vi.mocked(usePreset)).toHaveBeenCalledWith(presets.corporate);
    });

    it('setTheme returns the resolved name', () => {
        mockPageTheme('default');

        const { setTheme } = useTheme();
        expect(setTheme('default')).toBe('default');
        expect(setTheme('corporate')).toBe('corporate');
    });
});

// ── Tests: unknown-name fallback ──────────────────────────────────────────────

describe('useTheme — unknown name fallback to DEFAULT_THEME', () => {
    it('setTheme("nonexistent") returns DEFAULT_THEME', () => {
        mockPageTheme('default');

        const { setTheme } = useTheme();
        expect(setTheme('nonexistent')).toBe(DEFAULT_THEME);
    });

    it('setTheme("nonexistent") calls usePreset with the default preset', () => {
        mockPageTheme('default');

        const { setTheme } = useTheme();
        setTheme('nonexistent');

        expect(vi.mocked(usePreset)).toHaveBeenCalledWith(presets[DEFAULT_THEME]);
    });

    // ── Security-regression: prototype-chain leak guard (T5 fix) ─────────────
    //
    // The `in` operator walks the prototype chain, so `'constructor' in presets`
    // would be true on a plain object. The composable uses Object.hasOwn() to
    // ensure only real registry keys are accepted. These tests lock that guard:
    // a prototype-key name must NEVER be treated as a valid preset.

    it('setTheme("constructor") → DEFAULT_THEME [prototype-leak regression]', () => {
        mockPageTheme('default');
        const { setTheme } = useTheme();
        expect(setTheme('constructor')).toBe(DEFAULT_THEME);
    });

    it('setTheme("__proto__") → DEFAULT_THEME [prototype-leak regression]', () => {
        mockPageTheme('default');
        const { setTheme } = useTheme();
        expect(setTheme('__proto__')).toBe(DEFAULT_THEME);
    });

    it('setTheme("toString") → DEFAULT_THEME [prototype-leak regression]', () => {
        mockPageTheme('default');
        const { setTheme } = useTheme();
        expect(setTheme('toString')).toBe(DEFAULT_THEME);
    });
});

// ── Tests: currentTheme seeded from SSR prop ──────────────────────────────────

describe('useTheme — currentTheme seeded from SSR shared prop', () => {
    it('currentTheme is "default" when page.props.theme is "default"', () => {
        mockPageTheme('default');

        const { currentTheme } = useTheme();
        expect(currentTheme.value).toBe('default');
    });

    it('currentTheme is "corporate" when page.props.theme is "corporate"', () => {
        mockPageTheme('corporate');

        const { currentTheme } = useTheme();
        expect(currentTheme.value).toBe('corporate');
    });

    it('currentTheme falls back to DEFAULT_THEME when page.props.theme is undefined', () => {
        mockPageTheme(undefined);

        const { currentTheme } = useTheme();
        expect(currentTheme.value).toBe(DEFAULT_THEME);
    });

    it('currentTheme falls back to DEFAULT_THEME for an unknown SSR theme name', () => {
        mockPageTheme('nonexistent');

        const { currentTheme } = useTheme();
        expect(currentTheme.value).toBe(DEFAULT_THEME);
    });
});

// ── Tests: dark-mode orthogonality ────────────────────────────────────────────

describe('useTheme — dark-mode orthogonality', () => {
    it('setTheme does not add the .dark class to documentElement', () => {
        mockPageTheme('default');
        document.documentElement.classList.remove('dark');

        const { setTheme } = useTheme();
        setTheme('corporate');

        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('setTheme does not remove an existing .dark class', () => {
        mockPageTheme('corporate');
        document.documentElement.classList.add('dark');

        const { setTheme } = useTheme();
        setTheme('default');

        expect(document.documentElement.classList.contains('dark')).toBe(true);

        // Cleanup
        document.documentElement.classList.remove('dark');
    });

    it('setTheme does not modify documentElement.classList at all (only usePreset is called)', () => {
        mockPageTheme('default');
        const before = document.documentElement.className;

        const { setTheme } = useTheme();
        setTheme('corporate');

        expect(document.documentElement.className).toBe(before);
    });
});

// ── Tests: themeNames ─────────────────────────────────────────────────────────

describe('useTheme — themeNames', () => {
    it('themeNames contains all registry keys', () => {
        mockPageTheme('default');

        const { themeNames } = useTheme();
        expect(themeNames).toContain('default');
        expect(themeNames).toContain('corporate');
    });

    it('themeNames length matches the registry preset count', () => {
        mockPageTheme('default');

        const { themeNames } = useTheme();
        expect(themeNames).toHaveLength(Object.keys(presets).length);
    });
});

// ── Drift-guard — frontend registry key set ───────────────────────────────────
//
// Purpose: keep the frontend preset registry (stubs/resources/js/theme/presets/index.ts)
// and the backend config('starter-kit.themes') whitelist in sync.
// When a new preset is added, ALL THREE must be updated together:
//   - stubs/.../presets/index.ts  (frontend registry)
//   - config/starter-kit.php      (backend whitelist)
//   - The expected list below in this test (AND its PHP peer in UpdateAppearanceSettingsTest.php)
// Failing to update any one of them will break this test → drift caught before merge.

describe('Drift-guard — frontend preset registry key set', () => {
    it('preset registry keys match expected whitelist [drift-guard]', () => {
        const expected = ['default', 'corporate'];
        const actual = Object.keys(presets).sort();

        expect(actual).toEqual([...expected].sort());
    });

    it('DEFAULT_THEME is in the preset registry [drift-guard]', () => {
        expect(Object.hasOwn(presets, DEFAULT_THEME)).toBe(true);
    });
});
