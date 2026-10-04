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
    primitive: {
        // ── Border radius / Köşe yarıçapı (kit design language = 5px) ──
        // Material's stock UI radius is `sm: 4px`, which most non-form components
        // (Button, ToggleButton, Chip, Message, Menu, Tag, …) reference via
        // `{border.radius.sm}`. Bump it to 5px so they match the 5px form controls
        // (formField below), SkCard and sidebar nav (`--radius` in app.css). Other
        // steps (xs/md/lg/xl) keep Material's values.
        // Material'in stok UI radius'u `sm: 4px`; çoğu form-dışı bileşen (Button,
        // ToggleButton, Chip, Message, Menu, Tag, …) bunu `{border.radius.sm}` ile
        // kullanır. 5px'e çıkarıp 5px form alanları, SkCard ve sidebar nav ile
        // hizalarız. Diğer adımlar (xs/md/lg/xl) Material değerinde kalır.
        borderRadius: {
            sm: '5px',
        },
    },
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

        // ── Form field / Form alanı (kit override) ──
        // "Outline ferah + soft" giriş stili: 5px köşe, ferah dikey padding (≈46px
        // yükseklik) ve yumuşak (saydam) 3px focus halkası. TÜM form alanlarına
        // (InputText, Select, Textarea, DatePicker, Password vb.) küresel olarak
        // uygulanır. Material'in stok radius/focus değerlerini değiştirir.
        // (Material formField yalnızca şu token'ları tanır: paddingX/Y, borderRadius,
        // focusRing, transitionDuration — borderWidth tokenize değildir.)
        // "Outline ferah + soft" input style: 5px radius, generous vertical padding
        // (≈46px height) and a soft (translucent) 3px focus ring. Applied globally to
        // every form field. Overrides Material's stock radius/focus.
        formField: {
            paddingX: '0.875rem',
            paddingY: '0.6875rem',
            borderRadius: '5px',
            focusRing: {
                width: '3px',
                style: 'solid',
                color: 'color-mix(in srgb, {primary.color} 15%, transparent)',
                offset: '0',
                shadow: 'none',
            },
        },

        // ── Form field idle/hover border (kit override) ──
        // Softer, lighter idle outline than Material's stock (slate.400) so fields
        // don't read heavy; hover darkens one clear step. Lives on the formField TOKEN
        // (not unlayered CSS) so focus (primary) and invalid (red) borders still
        // cascade correctly. Light: idle slate.300, hover slate.400. Dark keeps
        // Material's balanced idle slate.600, hover slate.400.
        // Idle/hover kenar — Material stoktan (slate.400) daha açık/yumuşak idle, hover
        // bir adım koyu. CSS değil token üzerinden (focus/invalid cascade bozulmasın).
        // Light: idle slate.300, hover slate.400. Dark: idle slate.600, hover slate.400.
        // NOT: Preset renkleri app init'te uygulanır — değişiklik için TAM SAYFA YENİLE.
        //
        // Light primary sits on 700, not Material's 500: blue.500 (#2196f3) against white
        // is 3.12:1, under WCAG AA's 4.5:1 for button labels and links; blue.700 is 4.6:1.
        // Hover/active go darker rather than Material's lighter steps for the same reason.
        // Light primary 500 yerine 700'de: blue.500 beyaz üstünde 3.12:1 (AA 4.5:1 ister),
        // blue.700 4.6:1. Hover/active aynı sebeple açılmak yerine koyulaşır.
        colorScheme: {
            light: {
                primary: {
                    color: '{primary.700}',
                    contrastColor: '#ffffff',
                    hoverColor: '{primary.800}',
                    activeColor: '{primary.900}',
                },
                formField: {
                    borderColor: '{surface.300}',
                    hoverBorderColor: '{surface.400}',
                },
            },
            dark: {
                formField: {
                    borderColor: '{surface.600}',
                    hoverBorderColor: '{surface.400}',
                },
            },
        },
    },
    components: {
        // ── Button / Buton (kit override) ──
        // Kit-specific horizontal padding + 5px radius (kit design language). All other
        // button spacing/sizing follows Material. `border.radius.sm` already lands buttons
        // on 5px via the primitive above; this pins it explicitly on the button token so
        // it is guaranteed regardless of which scale step the style references.
        // Kit'e özel yatay padding + 5px köşe (kit tasarım dili). Diğer tüm buton
        // boşluk/boyutları Material'i izler. Üstteki primitive `border.radius.sm` zaten
        // butonu 5px'e taşır; bu, style hangi adımı kullanırsa kullansın garanti olsun
        // diye buton token'ında açıkça sabitlenmiştir.
        button: {
            paddingX: '1rem',
            borderRadius: '5px',
            // Material's solid severities put white on the 500 step, which fails WCAG AA
            // (orange 2.15:1, red 3.68:1, green 2.78:1). Red/green/sky move to the first
            // step that clears 4.5:1; no orange step does, so warn keeps its fill and
            // takes dark text instead. Hover/active go darker, not lighter.
            // Material'in solid severity'leri 500 üstüne beyaz yazı koyar, AA'yı geçmez.
            // Kırmızı/yeşil/sky 4.5:1'i geçen ilk tona iner; turuncuda böyle ton yok,
            // warn dolgusunu korur ve koyu yazı alır.
            colorScheme: {
                light: {
                    root: {
                        warn: {
                            color: '{surface.950}',
                            hoverColor: '{surface.950}',
                            activeColor: '{surface.950}',
                        },
                        danger: {
                            background: '{red.700}',
                            hoverBackground: '{red.800}',
                            activeBackground: '{red.900}',
                            borderColor: '{red.700}',
                            hoverBorderColor: '{red.800}',
                            activeBorderColor: '{red.900}',
                            focusRing: { color: '{red.700}' },
                        },
                        success: {
                            background: '{green.800}',
                            hoverBackground: '{green.900}',
                            activeBackground: '{green.950}',
                            borderColor: '{green.800}',
                            hoverBorderColor: '{green.900}',
                            activeBorderColor: '{green.950}',
                            focusRing: { color: '{green.800}' },
                        },
                        info: {
                            background: '{sky.800}',
                            hoverBackground: '{sky.900}',
                            activeBackground: '{sky.950}',
                            borderColor: '{sky.800}',
                            hoverBorderColor: '{sky.900}',
                            activeBorderColor: '{sky.950}',
                            focusRing: { color: '{sky.800}' },
                        },
                    },
                },
            },
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
