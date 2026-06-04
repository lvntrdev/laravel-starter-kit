import { definePreset } from '@primevue/themes';
import Material from '@primevue/themes/material';

/**
 * "Corporate" theme preset / "Kurumsal" tema preset'i
 *
 * A second, minimal alternative preset that proves the multi-preset
 * pipeline works end to end. It shares the same Material base and token
 * structure as `default`, but swaps the brand identity to a calmer
 * corporate teal and softens the corner radii — enough of a visual delta
 * to confirm a runtime preset swap without redesigning the whole UI.
 *
 * Multi-preset boru hattının uçtan uca çalıştığını kanıtlayan ikinci,
 * minimal bir alternatif preset. `default` ile aynı Material tabanını ve
 * token yapısını paylaşır; yalnızca marka rengini daha sakin bir kurumsal
 * teal'e çevirir ve köşe yuvarlaklığını yumuşatır — tüm arayüzü yeniden
 * tasarlamadan runtime preset swap'ını doğrulamaya yetecek görsel fark.
 *
 * Keep this intentionally small: a primary palette + a few semantic token
 * differences is the whole point. Larger theme libraries are future work.
 *
 * @see ./default.ts for the fully documented token reference.
 * @see ./index.ts for how presets are registered and selected.
 */
const CorporatePreset = definePreset(Material, {
    primitive: {
        // Slightly rounder corners than `default` to read as a distinct brand.
        // `default`'a göre biraz daha yuvarlak köşeler — ayrı bir marka hissi.
        borderRadius: {
            none: '0',
            xs: '2px',
            sm: '6px',
            md: '8px',
            lg: '12px',
            xl: '16px',
        },
    },

    semantic: {
        transitionDuration: '0.2s',

        focusRing: {
            width: '2px',
            style: 'solid',
            color: '{primary.color}',
            offset: '2px',
        },

        iconSize: '1rem',
        anchorGutter: '2px',

        // ── Primary Color / Birincil Renk ──
        // Corporate teal — the single most visible difference from `default`.
        // Kurumsal teal — `default`'tan en görünür tek fark.
        primary: {
            50: '#E6FAF7',
            100: '#CCF5EF',
            200: '#99EBDF',
            300: '#5FDAC9',
            400: '#2BC2AE',
            500: '#0F9E8C',
            600: '#0C7E70',
            700: '#0A6358',
            800: '#074A42',
            900: '#05322D',
            950: '#03211E',
        },

        // Form fields inherit the same dense sizing as `default` so existing
        // layouts/text-fit are unaffected when this preset is active.
        // Form alanları `default` ile aynı yoğun boyutlandırmayı miras alır;
        // bu preset aktifken mevcut yerleşim/metin uyumu bozulmaz.
        formField: {
            paddingX: '0.75rem',
            paddingY: '0.9rem',
            sm: {
                fontSize: '0.875rem',
                paddingX: '0.625rem',
                paddingY: '0.375rem',
            },
            lg: {
                fontSize: '1.125rem',
                paddingX: '0.875rem',
                paddingY: '0.625rem',
            },
            borderRadius: '{borderRadius.sm}',
            focusRing: {
                width: '0',
                style: 'none',
                color: 'transparent',
                offset: '0',
            },
            transitionDuration: '{transitionDuration}',
        },

        content: {
            borderRadius: '{borderRadius.sm}',
        },

        mask: {
            transitionDuration: '0.15s',
        },

        navigation: {
            list: {
                padding: '0.25rem',
                gap: '2px',
            },
            item: {
                padding: '0.5rem 0.75rem',
                borderRadius: '{borderRadius.sm}',
                gap: '0.5rem',
            },
            submenuLabel: {
                padding: '0.5rem 0.75rem',
                fontWeight: '600',
            },
            submenuIcon: {
                size: '0.875rem',
            },
        },

        overlay: {
            select: {
                borderRadius: '{borderRadius.sm}',
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
            popover: {
                borderRadius: '{borderRadius.sm}',
                padding: '0.75rem',
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
            modal: {
                borderRadius: '{borderRadius.md}',
                padding: '0',
                shadow: '0 24px 60px -20px rgba(15, 14, 25, 0.35), 0 6px 20px -6px rgba(15, 14, 25, 0.18)',
            },
            navigation: {
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
        },

        list: {
            padding: '0.25rem',
            gap: '2px',
            header: {
                padding: '0.5rem 0.75rem 0.25rem',
            },
            option: {
                padding: '0.5rem 0.75rem',
                borderRadius: '{borderRadius.sm}',
            },
            optionGroup: {
                padding: '0.5rem 0.75rem',
                fontWeight: '600',
            },
        },

        colorScheme: {
            light: {
                surface: {
                    0: '#ffffff',
                    50: '{slate.50}',
                    100: '{slate.100}',
                    200: '{slate.200}',
                    300: '{slate.300}',
                    400: '{slate.400}',
                    500: '{slate.500}',
                    600: '{slate.600}',
                    700: '{slate.700}',
                    800: '{slate.800}',
                    900: '{slate.900}',
                    950: '{slate.950}',
                },

                primary: {
                    color: '{primary.500}',
                    contrastColor: '#ffffff',
                    hoverColor: '{primary.600}',
                    activeColor: '{primary.700}',
                },

                highlight: {
                    background: '{primary.50}',
                    focusBackground: '{primary.100}',
                    color: '{primary.700}',
                    focusColor: '{primary.800}',
                },

                formField: {
                    background: '{surface.0}',
                    disabledBackground: '{surface.100}',
                    filledBackground: '{surface.50}',
                    filledHoverBackground: '{surface.50}',
                    filledFocusBackground: '{surface.50}',
                    borderColor: '{surface.300}',
                    hoverBorderColor: '{surface.400}',
                    focusBorderColor: '{primary.color}',
                    invalidBorderColor: '{red.400}',
                    color: '{surface.700}',
                    disabledColor: '{surface.500}',
                    placeholderColor: '{surface.500}',
                    invalidPlaceholderColor: '{red.600}',
                    floatLabelColor: '{surface.500}',
                    floatLabelFocusColor: '{primary.600}',
                    floatLabelActiveColor: '{surface.500}',
                    floatLabelInvalidColor: '{red.400}',
                    iconColor: '{surface.400}',
                    shadow: '0 0 #0000',
                },

                text: {
                    color: '{surface.700}',
                    hoverColor: '{surface.800}',
                    mutedColor: '{surface.500}',
                    hoverMutedColor: '{surface.600}',
                },
            },

            dark: {
                surface: {
                    0: '#ffffff',
                    50: '{slate.50}',
                    100: '{slate.100}',
                    200: '{slate.200}',
                    300: '{slate.300}',
                    400: '{slate.400}',
                    500: '{slate.500}',
                    600: '{slate.600}',
                    700: '{slate.700}',
                    800: '{slate.800}',
                    900: '{slate.900}',
                    950: '{slate.950}',
                },

                primary: {
                    color: '{primary.400}',
                    contrastColor: '{surface.900}',
                    hoverColor: '{primary.300}',
                    activeColor: '{primary.200}',
                },

                highlight: {
                    background: 'color-mix(in srgb, {primary.400}, transparent 84%)',
                    focusBackground: 'color-mix(in srgb, {primary.400}, transparent 76%)',
                    color: 'rgba(255,255,255,.87)',
                    focusColor: 'rgba(255,255,255,.87)',
                },

                formField: {
                    background: '{surface.950}',
                    disabledBackground: '{surface.700}',
                    filledBackground: '{surface.800}',
                    filledHoverBackground: '{surface.800}',
                    filledFocusBackground: '{surface.800}',
                    borderColor: '{surface.600}',
                    hoverBorderColor: '{surface.500}',
                    focusBorderColor: '{primary.color}',
                    invalidBorderColor: '{red.300}',
                    color: '{surface.0}',
                    disabledColor: '{surface.400}',
                    placeholderColor: '{surface.400}',
                    invalidPlaceholderColor: '{red.400}',
                    floatLabelColor: '{surface.400}',
                    floatLabelFocusColor: '{primary.color}',
                    floatLabelActiveColor: '{surface.400}',
                    floatLabelInvalidColor: '{red.300}',
                    iconColor: '{surface.400}',
                    shadow: '0 0 #0000',
                },

                text: {
                    color: '{surface.0}',
                    hoverColor: '{surface.0}',
                    mutedColor: '{surface.400}',
                    hoverMutedColor: '{surface.300}',
                },
            },
        },
    },

    components: {
        button: {
            paddingX: '1rem',
        },
    },

    directives: {},
});

export default CorporatePreset;
