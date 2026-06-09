import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/aura';

/**
 * Custom PrimeVue theme preset extending Aura.
 * Aura temasını genişleten özel PrimeVue tema preset'i.
 *
 * Reset to Aura defaults — all custom spacing/padding/radius/size/color overrides
 * (including the primary palette) were removed so the theme uses Aura's stock
 * values. The primary brand color therefore defaults to Aura's emerald; the
 * runtime accent system (useAccentColor.ts → DEFAULT_PRIMARY) is kept in sync
 * with this and also defaults to emerald. The only kit-specific tokens kept here
 * are Button and Tag.
 *
 * Aura defaultlarına sıfırlandı — tüm özel boşluk/padding/radius/boyut/renk
 * override'ları (primary palet dahil) kaldırıldı; tema Aura'nın stok değerlerini
 * kullanır. Birincil marka rengi böylece Aura'nın emerald'ına döner; runtime
 * accent sistemi (useAccentColor.ts → DEFAULT_PRIMARY) bununla senkron tutulur ve
 * o da emerald'a döner. Burada korunan tek kit'e özel token'lar Button ve Tag.
 *
 * @see https://primevue.org/theming/styled/#definepreset
 * @see https://primevue.org/theming/styled/#tokens
 */
const AppPreset = definePreset(Aura, {
    components: {
        // ── Button / Buton (kit override) ──
        // Kit-specific horizontal padding. All other button spacing/sizing follows Aura.
        // Kit'e özel yatay padding. Diğer tüm buton boşluk/boyutları Aura'yı izler.
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
