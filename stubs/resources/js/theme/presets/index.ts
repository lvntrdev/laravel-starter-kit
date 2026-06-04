import defaultPreset from './default';
import corporatePreset from './corporate';

/**
 * Theme Preset Registry / Tema Preset Kaydı
 *
 * Single source of truth for all selectable theme presets in the kit.
 * Each entry maps a stable preset name (the value persisted in
 * `appearance.theme` settings and validated by the backend whitelist)
 * to a PrimeVue `definePreset` object.
 *
 * Kit içinde seçilebilir tüm tema preset'lerinin tek kaynağı.
 * Her giriş, sabit bir preset adını (`appearance.theme` ayarında
 * saklanan ve backend whitelist'i ile doğrulanan değer) bir PrimeVue
 * `definePreset` objesine eşler.
 *
 * Adding a preset / Preset ekleme:
 *   1. Create `presets/<name>.ts` exporting a `definePreset` default.
 *      `presets/<name>.ts` oluşturun, `definePreset` default export edin.
 *   2. Register it below; the name becomes part of the selectable whitelist.
 *      Aşağıya kaydedin; ad seçilebilir whitelist'in parçası olur.
 *   3. Mirror the name in the backend whitelist (`config/starter-kit.php`).
 *      Adı backend whitelist'ine de ekleyin (`config/starter-kit.php`).
 */
export const presets = {
    default: defaultPreset,
    corporate: corporatePreset,
} as const;

/** Valid preset names — derived from the registry, no hardcoded list. */
/** Geçerli preset adları — registry'den türetilir, sabit liste yok. */
export type SkThemeName = keyof typeof presets;

/**
 * A registered PrimeVue preset object as produced by `definePreset`.
 * Derived from the registry so the type stays accurate regardless of the
 * upstream `definePreset` return typing.
 *
 * `definePreset` ile üretilen, kayıtlı bir PrimeVue preset objesi.
 * Registry'den türetildiği için upstream `definePreset` dönüş tipinden
 * bağımsız olarak doğru kalır.
 */
export type SkThemePreset = (typeof presets)[SkThemeName];

/** The fallback preset name used when a stored/shared value is unknown. */
/** Saklanan/paylaşılan değer bilinmiyorsa kullanılan yedek preset adı. */
export const DEFAULT_THEME: SkThemeName = 'default';

/**
 * Resolve a (possibly untrusted) name to a registered preset, falling back
 * to the default preset when the name is unknown. Never returns undefined,
 * so callers cannot accidentally apply an arbitrary/empty preset.
 *
 * (Güvenilmeyebilecek) bir adı kayıtlı bir preset'e çözer; ad bilinmiyorsa
 * default preset'e düşer. Asla undefined dönmez, böylece çağıranlar yanlışlıkla
 * keyfi/boş bir preset uygulayamaz.
 */
export function resolvePreset(name: string | null | undefined): SkThemePreset {
    // `Object.hasOwn` (not the `in` operator) so inherited Object.prototype
    // keys (`constructor`, `__proto__`, `toString`, `hasOwnProperty`, …) are
    // NOT treated as registered presets. With `in`, `resolvePreset('constructor')`
    // would return the `Object` constructor function instead of falling back to
    // default, and that bogus value would be fed to PrimeVue's preset engine.
    if (name && Object.hasOwn(presets, name)) {
        return presets[name as SkThemeName];
    }
    return presets[DEFAULT_THEME];
}

export default presets;
