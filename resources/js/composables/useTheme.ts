// resources/js/composables/useTheme.ts
import { usePage } from '@inertiajs/vue3';
import { usePreset } from '@primevue/themes';
import type { SharedPageProps } from '@/types';
import { resolvePreset, DEFAULT_THEME, presets, type SkThemeName } from '@/theme/presets';

/**
 * Composable for the app-wide runtime theme (color preset).
 *
 * The active preset name arrives as the Inertia shared prop `theme`
 * (set by the backend from `appearance.theme`; see HandleInertiaRequests).
 * `app.ts` already feeds that same resolved preset to PrimeVue's initial
 * config so the very first SSR render paints the correct theme — this
 * composable handles deploy-less runtime swaps after hydration.
 *
 * Orthogonal to `useDarkMode`: the color preset and light/dark mode are two
 * independent axes. `setTheme()` NEVER touches the `.dark` class on <html>;
 * dark mode stays entirely owned by `useDarkMode`.
 *
 * Çalışma-zamanı app-geneli tema (renk preset'i) composable'ı.
 *
 * Aktif preset adı Inertia shared-prop `theme` olarak gelir (backend
 * `appearance.theme`'den paylaşır). `app.ts` ilk SSR render'ında aynı
 * çözülmüş preset'i PrimeVue config'ine verir (FOUC yok); bu composable
 * hydrate sonrası deploy'suz runtime swap'ı yönetir.
 *
 * `useDarkMode` ile ortogonal: renk preset'i ve light/dark iki bağımsız
 * eksendir. `setTheme()` ASLA <html> üzerindeki `.dark` class'ına dokunmaz.
 *
 * Usage / Kullanım:
 *   const { currentTheme, setTheme, themeNames } = useTheme();
 *   setTheme('corporate'); // applies the preset at runtime
 */
export function useTheme() {
    const page = usePage<SharedPageProps>();

    /**
     * Resolve the (possibly untrusted) shared-prop name to a known preset
     * name. An unknown/missing value collapses to DEFAULT_THEME so the
     * reactive state never reflects an arbitrary string.
     */
    function resolveName(name: string | null | undefined): SkThemeName {
        // `Object.hasOwn`, not `in`: the `in` operator walks the prototype
        // chain, so `'constructor' in presets` (and `__proto__`, `toString`,
        // `hasOwnProperty`, …) are all true on a plain object literal. That
        // would let an untrusted shared-prop name set `currentTheme` to a
        // bogus inherited key. `Object.hasOwn` only matches real registry entries.
        return name && Object.hasOwn(presets, name) ? (name as SkThemeName) : DEFAULT_THEME;
    }

    /** The active preset name. Seeded from the SSR shared prop `theme`. */
    const currentTheme = ref<SkThemeName>(resolveName(page.props.theme));

    /** All selectable preset names — derived from the registry. */
    const themeNames = Object.keys(presets) as SkThemeName[];

    /**
     * Apply a theme preset at runtime.
     *
     * Unknown names fall back to the default preset (via `resolvePreset`),
     * and `currentTheme` always reflects the *resolved* name — callers can
     * never drive the UI to an arbitrary/empty preset.
     *
     * SSR-safe: `usePreset` injects a `<style>` element into the document,
     * so it must run client-side only. On the server there is no DOM; we
     * skip the swap (the initial preset has already been baked into the
     * PrimeVue config by `app.ts`) and only update the reactive name.
     */
    function setTheme(name: string | null | undefined): SkThemeName {
        const resolved = resolveName(name);
        currentTheme.value = resolved;

        if (typeof document !== 'undefined') {
            usePreset(resolvePreset(resolved));
        }

        return resolved;
    }

    return {
        /** Reactive active preset name (read-only). */
        currentTheme: readonly(currentTheme),
        /** Selectable preset names from the registry. */
        themeNames,
        /** Apply a preset at runtime; returns the resolved name. */
        setTheme,
    };
}
