import { definePreset } from '@primevue/themes';
import Material from '@primevue/themes/material';

/**
 * Custom PrimeVue theme preset extending Material.
 * Material temasını genişleten özel PrimeVue tema preset'i.
 *
 * Uses Material's stock values — all custom spacing/padding/radius/size/color
 * overrides (including the primary palette) are left at Material defaults so the
 * theme uses Material's stock tokens. The primary brand color is driven by the
 * runtime accent system (useAccentColor.ts → DEFAULT_PRIMARY), which defaults to
 * emerald. The only kit-specific tokens kept here are Button and Tag.
 *
 * Material'in stok değerlerini kullanır — tüm özel boşluk/padding/radius/boyut/renk
 * override'ları (primary palet dahil) Material defaultlarında bırakılmıştır; tema
 * Material'in stok token'larını kullanır. Birincil marka rengi runtime accent
 * sistemi (useAccentColor.ts → DEFAULT_PRIMARY) tarafından sürülür ve emerald'a
 * döner. Burada korunan tek kit'e özel token'lar Button ve Tag.
 *
 * @see https://primevue.org/theming/styled/#definepreset
 * @see https://primevue.org/theming/styled/#tokens
 */
const AppPreset = definePreset(Material, {
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
