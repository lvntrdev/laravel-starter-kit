import { definePreset } from '@primevue/themes';
import Material from '@primevue/themes/material';

/**
 * Custom PrimeVue theme preset extending Material.
 * Material temasını genişleten özel PrimeVue tema preset'i.
 *
 * Uses Material's stock values for spacing/padding/radius/size — only the primary
 * palette is overridden to BLUE so the kit's default brand color is blue everywhere,
 * including pages that never mount the admin accent system (e.g. AuthLayout). This is
 * the single source of truth for the default primary; the runtime accent system
 * (useAccentColor.ts → DEFAULT_PRIMARY) hands back the same `{blue.x}` references when
 * the accent is reset to "default". The only kit-specific component tokens are Button
 * and Tag.
 *
 * Material'in stok boşluk/padding/radius/boyut değerlerini kullanır — yalnızca primary
 * palet BLUE'ya override edilmiştir; böylece kit'in default marka rengi her yerde
 * mavidir (admin accent sistemini hiç mount etmeyen sayfalar dahil, örn. AuthLayout).
 * Default primary için tek doğruluk kaynağı burasıdır; runtime accent sistemi
 * (useAccentColor.ts → DEFAULT_PRIMARY) accent "default"a sıfırlanınca aynı `{blue.x}`
 * referanslarını geri verir. Korunan tek kit'e özel bileşen token'ları Button ve Tag.
 *
 * @see https://primevue.org/theming/styled/#definepreset
 * @see https://primevue.org/theming/styled/#tokens
 */
const AppPreset = definePreset(Material, {
    semantic: {
        // ── Primary / Birincil renk (kit default) ──
        // Override Material's stock emerald primary with blue. Drives buttons, links,
        // focus rings, active states. Material's `{blue.x}` primitives resolve here.
        // Material'in stok emerald primary'sini blue ile değiştirir. Buton, link, focus
        // ring, aktif durumları sürer. Material'in `{blue.x}` primitive'leri çözülür.
        primary: {
            50: '{blue.50}',
            100: '{blue.100}',
            200: '{blue.200}',
            300: '{blue.300}',
            400: '{blue.400}',
            500: '{blue.500}',
            600: '{blue.600}',
            700: '{blue.700}',
            800: '{blue.800}',
            900: '{blue.900}',
            950: '{blue.950}',
        },
    },
    components: {
        // ── Button / Buton (kit override) ──
        // Kit-specific horizontal padding. All other button spacing/sizing follows Material.
        // Kit'e özel yatay padding. Diğer tüm buton boşluk/boyutları Material'i izler.
        button: {
            paddingX: '1rem',
        },

        // ── Tag / Etiket (kit override) ──
        // Reserved for kit-specific Tag tokens. Currently none are overridden here;
        // Tag styling (severity colors via data-p) lives in CSS. Uncomment to override.
        // Kit'e özel Tag token'ları için ayrılmıştır. Şu an burada override yok;
        // Tag stili (data-p ile severity renkleri) CSS'te. Override için yorumu kaldırın.
        // tag: {
        //     borderRadius: '{borderRadius.sm}',
        //     paddingX: '0.5rem',
        //     paddingY: '0.25rem',
        //     fontSize: '0.75rem',
        //     fontWeight: '600',
        //     gap: '0.25rem',
        // },
    },
});

export default AppPreset;
