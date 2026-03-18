import { definePreset } from '@primevue/themes';
import Aura from '@primevue/themes/material';

/**
 * Custom PrimeVue theme preset extending Material.
 * Material temasını genişleten özel PrimeVue tema preset'i.
 *
 * Token Hierarchy / Token Hiyerarşisi:
 *   1. Primitive  → Base values (colors, radius, spacing) / Temel değerler (renkler, radius, boşluk)
 *   2. Semantic   → Contextual meanings (primary, surface, danger) / Bağlamsal anlamlar (birincil, yüzey, tehlike)
 *   3. Component  → Per-component overrides (button, card, etc.) / Bileşen bazlı geçersiz kılmalar
 *   4. Directive  → Per-directive overrides (tooltip, ripple) / Direktif bazlı geçersiz kılmalar
 *
 * Token Reference Syntax / Token Referans Sözdizimi:
 *   '{color.shade}' → e.g. '{indigo.500}' references the indigo 500 primitive token
 *   '{semantic}'    → e.g. '{primary.color}' references the resolved primary color
 *
 * @see https://primevue.org/theming/styled/#definepreset
 * @see https://primevue.org/theming/styled/#tokens
 */
const AppPreset = definePreset(Aura, {
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // PRIMITIVE DESIGN TOKENS
    // Temel Tasarım Token'ları
    // ─────────────────────────────────────────────────────────────────
    // The lowest level tokens. They define raw values that semantic and
    // component tokens reference. Changing these affects the entire theme.
    //
    // En düşük seviye token'lar. Tüm semantik ve bileşen token'larının
    // referans aldığı ham değerleri tanımlar. Bunları değiştirmek
    // temanın tamamını etkiler.
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    primitive: {
        // ── Border Radius / Köşe Yuvarlaklığı ──
        // Controls corner rounding across all components.
        // Tüm bileşenlerdeki köşe yuvarlaklığını kontrol eder.
        borderRadius: {
            none: '0',
            xs: '2px',
            sm: '4px',
            md: '6px',
            lg: '8px',
            xl: '12px',
        },

        // ── Custom Color Palette / Özel Renk Paleti ──
        // Define your own color scales (50-950) to use throughout the theme.
        // Tema genelinde kullanmak için kendi renk skalalarınızı (50-950) tanımlayın.
        // Once defined, reference them via '{mycolor.500}' syntax.
        // Tanımlandıktan sonra '{mycolor.500}' sözdizimi ile referans verin.
        //
        // brand: {
        //     50:  '#eff6ff',
        //     100: '#dbeafe',
        //     200: '#bfdbfe',
        //     300: '#93c5fd',
        //     400: '#60a5fa',
        //     500: '#3b82f6',
        //     600: '#2563eb',
        //     700: '#1d4ed8',
        //     800: '#1e40af',
        //     900: '#1e3a8a',
        //     950: '#172554',
        // },
    },

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // SEMANTIC DESIGN TOKENS
    // Anlamsal Tasarım Token'ları
    // ─────────────────────────────────────────────────────────────────
    // Semantic tokens map primitive tokens to contextual meanings.
    // They determine how colors/styles are applied across the UI.
    //
    // Anlamsal token'lar, temel token'ları bağlamsal anlamlara eşler.
    // Renk ve stillerin arayüz genelinde nasıl uygulanacağını belirler.
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    semantic: {
        // ── Transition Duration / Geçiş Süresi ──
        // Controls animation speed for all component transitions.
        // Tüm bileşen geçişlerinin animasyon hızını kontrol eder.
        transitionDuration: '0.2s',

        // ── Focus Ring / Odak Halkası ──
        // Keyboard-navigated focus indicator styling.
        // Klavye ile gezinti sırasında odak göstergesi stili.
        focusRing: {
            width: '2px', // Thickness / Kalınlık
            style: 'solid', // Border style / Kenarlık stili
            color: '{primary.color}', // Ring color / Halka rengi
            offset: '2px', // Offset from element / Öğeden uzaklık
        },

        // ── Icon Size / İkon Boyutu ──
        // Default icon size used across components.
        // Bileşenler genelinde kullanılan varsayılan ikon boyutu.
        iconSize: '1rem',

        // ── Anchor (Link) / Bağlantı ──
        // Default link styling across the theme.
        // Tema genelinde varsayılan bağlantı stili.
        anchorGutter: '2px', // Space around clickable area / Tıklanabilir alan etrafındaki boşluk

        // ── Primary Color / Birincil Renk ──
        // The main brand color used for buttons, links, active states, etc.
        // Butonlar, bağlantılar, aktif durumlar vb. için kullanılan ana marka rengi.
        // Change '{indigo.X}' to any palette: emerald, blue, violet, amber, rose, cyan, teal, etc.
        // '{indigo.X}' yerine herhangi bir palet kullanın: emerald, blue, violet, amber, rose, cyan, teal, vb.
        primary: {
            50: '#E6F0FF',
            100: '#CCE0FF',
            200: '#99C2FF',
            300: '#66A3FF',
            400: '#3385FF',
            500: '#0069FF',
            600: '#0054CC',
            700: '#003F99',
            800: '#002A66',
            900: '#001A40',
            950: '#001029',
        },

        // ── Form Field / Form Alanı ──
        // Global styling for all form inputs (InputText, Select, Textarea, etc.)
        // Tüm form girdileri için genel stil (InputText, Select, Textarea, vb.)
        formField: {
            paddingX: '0.75rem', // Horizontal padding / Yatay iç boşluk
            paddingY: '0.9rem', // Vertical padding / Dikey iç boşluk (48px total height at 14px root)
            sm: {
                fontSize: '0.875rem', // Small variant font size / Küçük boyut yazı tipi
                paddingX: '0.625rem',
                paddingY: '0.375rem',
            },
            lg: {
                fontSize: '1.125rem', // Large variant font size / Büyük boyut yazı tipi
                paddingX: '0.875rem',
                paddingY: '0.625rem',
            },
            borderRadius: '{borderRadius.sm}', // Corner rounding / Köşe yuvarlaklığı
            focusRing: {
                width: '0', // Focus ring inside form fields (0 = use outline instead)
                style: 'none', // Form alanlarında odak halkası (0 = bunun yerine outline kullan)
                color: 'transparent',
                offset: '0',
            },
            transitionDuration: '{transitionDuration}', // Animation speed / Animasyon hızı
        },

        // ── Content / İçerik ──
        // Controls content area styling (e.g., Card body, Dialog body).
        // İçerik alanı stilini kontrol eder (ör. Card gövdesi, Dialog gövdesi).
        content: {
            borderRadius: '{borderRadius.sm}',
        },

        // ── Mask / Maske ──
        // Overlay mask Background used behind dialogs, sidebars, etc.
        // Dialog, sidebar vb. arkasındaki kaplama maskesi arka planı.
        mask: {
            transitionDuration: '0.15s',
        },

        // ── Navigation / Navigasyon ──
        // Styling for menu, menubar, tabmenu and similar navigation components.
        // Menü, menü çubuğu, sekme menüsü ve benzeri navigasyon bileşenlerinin stili.
        navigation: {
            list: {
                padding: '0.25rem', // Inner padding of menu lists / Menü listelerinin iç boşluğu
                gap: '2px', // Space between menu items / Menü öğeleri arası boşluk
            },
            item: {
                padding: '0.5rem 0.75rem', // Menu item padding / Menü öğesi iç boşluğu
                borderRadius: '{borderRadius.sm}', // Menu item corner rounding / Menü öğesi köşe yuvarlaklığı
                gap: '0.5rem', // Space between icon and text / İkon ve metin arası boşluk
            },
            submenuLabel: {
                padding: '0.5rem 0.75rem', // Submenu label padding / Alt menü etiketi iç boşluğu
                fontWeight: '600', // Submenu label weight / Alt menü etiketi yazı kalınlığı
            },
            submenuIcon: {
                size: '0.875rem', // Submenu indicator icon size / Alt menü gösterge ikonu boyutu
            },
        },

        // ── Overlay / Kaplama ──
        // Controls styling for overlays: popups, dialogs, menus, popovers.
        // Kaplama stilini kontrol eder: popup'lar, diyaloglar, menüler, popover'lar.
        overlay: {
            select: {
                borderRadius: '{borderRadius.sm}', // Dropdown overlay radius / Açılır menü kaplaması yuvarlaklığı
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
            popover: {
                borderRadius: '{borderRadius.sm}', // Popover radius / Popover yuvarlaklığı
                padding: '0.75rem', // Popover inner padding / Popover iç boşluğu
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
            modal: {
                borderRadius: '{borderRadius.xl}', // Dialog/Modal radius / Dialog/Modal yuvarlaklığı
                padding: '1.25rem', // Dialog inner padding / Dialog iç boşluğu
                shadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)',
            },
            navigation: {
                shadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            },
        },

        // ── List / Liste ──
        // Styling for Listbox, OrderList and similar list-based components.
        // Listbox, OrderList ve benzeri liste tabanlı bileşenlerin stili.
        list: {
            padding: '0.25rem', // Inner padding of list container / Liste kapsayıcısının iç boşluğu
            gap: '2px', // Space between list items / Liste öğeleri arası boşluk
            header: {
                padding: '0.5rem 0.75rem 0.25rem', // List header padding / Liste başlığı iç boşluğu
            },
            option: {
                padding: '0.5rem 0.75rem', // List option padding / Liste seçeneği iç boşluğu
                borderRadius: '{borderRadius.sm}', // Option corner rounding / Seçenek köşe yuvarlaklığı
            },
            optionGroup: {
                padding: '0.5rem 0.75rem', // Option group header padding / Seçenek grubu başlığı iç boşluğu
                fontWeight: '600', // Option group header weight / Seçenek grubu yazı kalınlığı
            },
        },

        // ── Color Scheme / Renk Şeması ──
        // Mode-specific token overrides for light and dark themes.
        // Açık ve koyu temalar için moda özgü token geçersiz kılmaları.
        colorScheme: {
            // ── Light Mode / Açık Mod ──
            light: {
                // Surface colors define backgrounds and layering in light mode.
                // Surface renkleri açık modda arka planları ve katmanlamayı tanımlar.
                surface: {
                    0: '#ffffff', // Base white (cards, dialogs) / Temel beyaz (kartlar, diyaloglar)
                    50: '{slate.50}', // Lightest gray (hover backgrounds) / En açık gri (hover arka planları)
                    100: '{slate.100}', // Input backgrounds / Girdi arka planları
                    200: '{slate.200}', // Borders, dividers / Kenarlıklar, ayırıcılar
                    300: '{slate.300}', // Disabled borders / Devre dışı kenarlıklar
                    400: '{slate.400}', // Placeholder text / Yer tutucu metin
                    500: '{slate.500}', // Secondary text / İkincil metin
                    600: '{slate.600}', // Body text / Gövde metni
                    700: '{slate.700}', // Headings / Başlıklar
                    800: '{slate.800}', // Primary text / Birincil metin
                    900: '{slate.900}', // Strongest text / En güçlü metin
                    950: '{slate.950}', // Near-black / Siyaha yakın
                },

                // Primary color token overrides for light mode.
                // Açık mod için birincil renk token geçersiz kılmaları.
                primary: {
                    color: '{primary.500}', // Default primary / Varsayılan birincil
                    contrastColor: '#ffffff', // Text on primary bg / Birincil arka plan üzerindeki metin
                    hoverColor: '{primary.600}', // Hover state / Üzerine gelme durumu
                    activeColor: '{primary.700}', // Active/pressed state / Aktif/basılı durumu
                },

                // Highlight tokens control selected/active item styling.
                // Vurgu token'ları seçili/aktif öğe stilini kontrol eder.
                highlight: {
                    background: '{primary.50}', // Selected item bg / Seçili öğe arka planı
                    focusBackground: '{primary.100}', // Focused selected bg / Odaklı seçili arka plan
                    color: '{primary.700}', // Selected item text / Seçili öğe metni
                    focusColor: '{primary.800}', // Focused selected text / Odaklı seçili metin
                },

                // Form field mode-specific tokens for light mode.
                // Açık mod için form alanı moda özgü token'ları.
                formField: {
                    background: '{surface.0}', // Input background / Girdi arka planı
                    disabledBackground: '{surface.100}', // Disabled input bg / Devre dışı girdi arka planı
                    filledBackground: '{surface.50}', // Filled variant bg / Dolu varyant arka planı
                    filledHoverBackground: '{surface.50}', // Filled variant hover bg / Dolu varyant hover arka planı
                    filledFocusBackground: '{surface.50}', // Filled variant focus bg / Dolu varyant odak arka planı
                    borderColor: '{surface.300}', // Default border / Varsayılan kenarlık
                    hoverBorderColor: '{surface.400}', // Hover border / Hover kenarlık
                    focusBorderColor: '{primary.color}', // Focus border / Odak kenarlığı
                    invalidBorderColor: '{red.400}', // Validation error border / Doğrulama hatası kenarlığı
                    color: '{surface.700}', // Input text color / Girdi metin rengi
                    disabledColor: '{surface.500}', // Disabled text color / Devre dışı metin rengi
                    placeholderColor: '{surface.500}', // Placeholder color / Yer tutucu rengi
                    invalidPlaceholderColor: '{red.600}', // Error placeholder / Hata yer tutucu rengi
                    floatLabelColor: '{surface.500}', // Float label color / Kayan etiket rengi
                    floatLabelFocusColor: '{primary.600}', // Float label focus / Kayan etiket odak rengi
                    floatLabelActiveColor: '{surface.500}', // Float label active / Kayan etiket aktif rengi
                    floatLabelInvalidColor: '{red.400}', // Float label error / Kayan etiket hata rengi
                    iconColor: '{surface.400}', // Input icon color / Girdi ikon rengi
                    shadow: '0 0 #0000', // Input box shadow / Girdi kutu gölgesi
                },

                // Text color tokens for light mode.
                // Açık mod için metin renk token'ları.
                text: {
                    color: '{surface.700}', // Default text / Varsayılan metin
                    hoverColor: '{surface.800}', // Hover text / Hover metin
                    mutedColor: '{surface.500}', // Muted/faded text / Soluk metin
                    hoverMutedColor: '{surface.600}', // Hover muted text / Hover soluk metin
                },
            },

            // ── Dark Mode / Koyu Mod ──
            dark: {
                // Surface colors for dark mode — using gray palette to match auth pages.
                // Koyu mod için surface renkleri — auth sayfalarıyla uyumlu gray paleti.
                surface: {
                    0: '#ffffff', // Text on dark bg / Koyu arka plan üzerindeki metin
                    50: '{gray.50}', // Rarely used in dark mode / Koyu modda nadiren kullanılır
                    100: '{gray.100}',
                    200: '{gray.200}',
                    300: '{gray.300}',
                    400: '{gray.400}', // Placeholder text / Yer tutucu metin
                    500: '{gray.500}', // Muted text / Soluk metin
                    600: '{gray.600}', // Borders / Kenarlıklar
                    700: '{gray.700}', // Card backgrounds / Kart arka planları
                    800: '{gray.800}', // Slightly elevated surfaces / Hafif yükseltilmiş yüzeyler
                    900: '{gray.900}', // Main background / Ana arka plan
                    950: '{gray.950}', // Deepest background / En derin arka plan
                },

                // Primary color token overrides for dark mode.
                // Koyu mod için birincil renk token geçersiz kılmaları.
                // In dark mode, lighter primary shades provide better contrast.
                // Koyu modda daha açık birincil tonlar daha iyi kontrast sağlar.
                primary: {
                    color: '{primary.400}', // Default primary / Varsayılan birincil
                    contrastColor: '{surface.900}', // Text on primary bg / Birincil arka plan üzerindeki metin
                    hoverColor: '{primary.300}', // Hover state / Üzerine gelme durumu
                    activeColor: '{primary.200}', // Active state / Aktif durumu
                },

                // Highlight tokens for dark mode — uses color-mix for transparency.
                // Koyu mod vurgu token'ları — saydamlık için color-mix kullanır.
                highlight: {
                    background: 'color-mix(in srgb, {primary.400}, transparent 84%)',
                    focusBackground: 'color-mix(in srgb, {primary.400}, transparent 76%)',
                    color: 'rgba(255,255,255,.87)',
                    focusColor: 'rgba(255,255,255,.87)',
                },

                // Form field mode-specific tokens for dark mode.
                // Koyu mod için form alanı moda özgü token'ları.
                formField: {
                    background: '{surface.950}', // Input background / Girdi arka planı
                    disabledBackground: '{surface.700}', // Disabled bg / Devre dışı arka plan
                    filledBackground: '{surface.800}', // Filled variant bg / Dolu varyant arka planı
                    filledHoverBackground: '{surface.800}',
                    filledFocusBackground: '{surface.800}',
                    borderColor: '{surface.600}', // Default border / Varsayılan kenarlık
                    hoverBorderColor: '{surface.500}', // Hover border / Hover kenarlık
                    focusBorderColor: '{primary.color}', // Focus border / Odak kenarlığı
                    invalidBorderColor: '{red.300}', // Validation error / Doğrulama hatası
                    color: '{surface.0}', // Input text / Girdi metni
                    disabledColor: '{surface.400}', // Disabled text / Devre dışı metin
                    placeholderColor: '{surface.400}', // Placeholder / Yer tutucu
                    invalidPlaceholderColor: '{red.400}',
                    floatLabelColor: '{surface.400}',
                    floatLabelFocusColor: '{primary.color}',
                    floatLabelActiveColor: '{surface.400}',
                    floatLabelInvalidColor: '{red.300}',
                    iconColor: '{surface.400}',
                    shadow: '0 0 #0000',
                },

                // Text color tokens for dark mode.
                // Koyu mod için metin renk token'ları.
                text: {
                    color: '{surface.0}', // Default text / Varsayılan metin
                    hoverColor: '{surface.0}', // Hover text / Hover metin
                    mutedColor: '{surface.400}', // Muted text / Soluk metin
                    hoverMutedColor: '{surface.300}', // Hover muted / Hover soluk metin
                },
            },
        },
    },

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // COMPONENT DESIGN TOKENS
    // Bileşen Tasarım Token'ları
    // ─────────────────────────────────────────────────────────────────
    // Override tokens for specific PrimeVue components. Each component
    // has its own set of tokens documented on its page under "Theming".
    //
    // PrimeVue bileşenlerine özel token'ları geçersiz kılın. Her bileşenin
    // kendi token seti, dokümantasyon sayfasında "Theming" altında yer alır.
    //
    // @see https://primevue.org/button/#theming
    // @see https://primevue.org/datatable/#theming
    // @see https://primevue.org/dialog/#theming
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    components: {
        // ── Button / Buton ──
        // Used for all button variants: primary, secondary, outlined, text, etc.
        // Tüm buton varyantları için kullanılır: primary, secondary, outlined, text, vb.
        // button paddingY is overridden via CSS variable in _base.scss
        // to match form field height (Material theme ignores component token override)
        button: {
            paddingX: '1rem',
        },
        // ── Card / Kart ──
        // Panel-like component for content grouping.
        // İçerik gruplama için panel benzeri bileşen.
        // card: {
        //     shadow: '0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1)',
        //     borderRadius: '{borderRadius.xl}',
        //     body: {
        //         padding: '1.25rem', // Card body padding / Kart gövdesi iç boşluğu
        //         gap: '0.75rem',     // Gap between card sections / Kart bölümleri arası boşluk
        //     },
        //     title: {
        //         fontSize: '1.25rem',  // Card title size / Kart başlık boyutu
        //         fontWeight: '600',
        //     },
        //     subtitle: {
        //         color: '{text.mutedColor}', // Card subtitle color / Kart alt başlık rengi
        //     },
        // },
        // ── InputText / Metin Girdisi ──
        // Text input field — styling also applies to Password, InputNumber, etc.
        // Metin girdi alanı — stil aynı zamanda Password, InputNumber vb. için de geçerlidir.
        // inputtext: {
        //     borderRadius: '{borderRadius.sm}',
        //     shadow: '{formField.shadow}',
        //     paddingX: '{formField.paddingX}',
        //     paddingY: '{formField.paddingY}',
        // },
        // ── Select (Dropdown) / Seçim Kutusu ──
        // Dropdown selection component.
        // Açılır seçim bileşeni.
        // select: {
        //     borderRadius: '{borderRadius.sm}',
        //     shadow: '{formField.shadow}',
        //     paddingX: '{formField.paddingX}',
        //     paddingY: '{formField.paddingY}',
        //     optionPadding: '{list.option.padding}',
        //     optionBorderRadius: '{list.option.borderRadius}',
        // },
        // ── DataTable / Veri Tablosu ──
        // Full-featured data table component.
        // Tam özellikli veri tablosu bileşeni.
        // datatable: {
        //     headerCellPadding: '0.75rem 1rem',     // Header cell padding / Başlık hücresi iç boşluğu
        //     headerCellBorderColor: '{surface.200}', // Header border / Başlık kenarlığı
        //     bodyCellPadding: '0.75rem 1rem',        // Body cell padding / Gövde hücresi iç boşluğu
        //     rowToggleButtonSize: '1.75rem',          // Row expand button / Satır genişletme butonu
        //     sortIconColor: '{text.mutedColor}',      // Sort icon color / Sıralama ikon rengi
        //     filterRowPadding: '0.5rem 1rem',         // Filter row padding / Filtre satırı iç boşluğu
        //     paginatorPadding: '0.5rem 1rem',         // Paginator padding / Sayfalama iç boşluğu
        //     colorScheme: {
        //         light: {
        //             root: {
        //                 borderColor: '{surface.200}',          // Table border / Tablo kenarlığı
        //             },
        //             bodyRow: {
        //                 stripedBackground: '{surface.50}',     // Striped row bg / Çizgili satır arka planı
        //                 hoverBackground: '{surface.100}',      // Hovered row / Üzerine gelinen satır
        //                 selectedBackground: '{highlight.background}', // Selected row / Seçili satır
        //             },
        //         },
        //         dark: {
        //             root: {
        //                 borderColor: '{surface.700}',
        //             },
        //             bodyRow: {
        //                 stripedBackground: '{surface.800}',
        //                 hoverBackground: '{surface.700}',
        //                 selectedBackground: '{highlight.background}',
        //             },
        //         },
        //     },
        // },
        // ── Dialog / Diyalog ──
        // Modal dialog component.
        // Kalıcı diyalog bileşeni.
        // dialog: {
        //     borderRadius: '{overlay.modal.borderRadius}',
        //     padding: '{overlay.modal.padding}',
        //     shadow: '{overlay.modal.shadow}',
        //     title: {
        //         fontSize: '1.25rem',  // Dialog title size / Diyalog başlık boyutu
        //         fontWeight: '600',
        //     },
        // },
        // ── Toast / Bildirim ──
        // Notification messages that appear on screen.
        // Ekranda görünen bildirim mesajları.
        // toast: {
        //     borderRadius: '{borderRadius.sm}',
        //     borderWidth: '1px',
        //     shadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)',
        //     summary: {
        //         fontWeight: '700',    // Toast title weight / Bildirim başlık kalınlığı
        //         fontSize: '1rem',
        //     },
        //     detail: {
        //         fontWeight: '500',    // Toast detail weight / Bildirim detay kalınlığı
        //         fontSize: '0.875rem',
        //     },
        // },
        // ── Tag / Etiket ──
        // Small label/badge component for status indicators, categories, etc.
        // Durum göstergeleri, kategoriler vb. için küçük etiket/rozet bileşeni.
        // tag: {
        //     borderRadius: '{borderRadius.sm}', // Tag corner rounding / Etiket köşe yuvarlaklığı
        //     paddingX: '0.5rem',
        //     paddingY: '0.25rem',
        //     fontSize: '0.75rem',
        //     fontWeight: '600',
        //     gap: '0.25rem',
        // },
        // ── Paginator / Sayfalama ──
        // Pagination component used standalone or within DataTable.
        // Tek başına veya DataTable içinde kullanılan sayfalama bileşeni.
        // paginator: {
        //     padding: '0.5rem 1rem',
        //     gap: '0.25rem',
        //     borderRadius: '{borderRadius.sm}',
        //     navButtonBorderRadius: '{borderRadius.sm}', // Nav button radius / Gezinti butonu yuvarlaklığı
        //     currentPageReportColor: '{text.mutedColor}',
        // },
        // ── Tabs / Sekmeler ──
        // Tabbed navigation component.
        // Sekmeli navigasyon bileşeni.
        // tabs: {
        //     tabPadding: '0.75rem 1.25rem',   // Tab item padding / Sekme öğesi iç boşluğu
        //     tabFontWeight: '600',             // Tab label weight / Sekme yazı kalınlığı
        //     tabBorderWidth: '2px',            // Active tab indicator / Aktif sekme göstergesi
        //     tabGap: '0',
        // },
        // ── Menu / Menü ──
        // Popup menu component.
        // Açılır menü bileşeni.
        // menu: {
        //     borderRadius: '{overlay.select.borderRadius}',
        //     shadow: '{overlay.select.shadow}',
        //     padding: '{navigation.list.padding}',
        //     itemPadding: '{navigation.item.padding}',
        //     itemBorderRadius: '{navigation.item.borderRadius}',
        //     itemGap: '{navigation.list.gap}',
        //     submenuLabelPadding: '{navigation.submenuLabel.padding}',
        //     submenuLabelFontWeight: '{navigation.submenuLabel.fontWeight}',
        // },
        // ── Breadcrumb / Ekmek Kırıntısı ──
        // breadcrumb: {
        //     padding: '1rem',
        //     gap: '0.25rem',
        //     itemColor: '{text.mutedColor}',       // Inactive link / Aktif olmayan bağlantı
        //     itemHoverColor: '{text.color}',        // Hover link / Hover bağlantı
        //     lastItemColor: '{text.color}',         // Current page / Mevcut sayfa
        //     separatorColor: '{text.mutedColor}',   // Separator icon / Ayırıcı ikon
        // },
        // ── Badge / Rozet ──
        // Numeric badge overlaid on icons or buttons.
        // İkon veya butonların üzerine yerleştirilen sayısal rozet.
        // badge: {
        //     borderRadius: '{borderRadius.xl}',
        //     fontSize: '0.75rem',
        //     fontWeight: '700',
        //     minWidth: '1.5rem',
        //     height: '1.5rem',
        //     padding: '0 0.5rem',
        // },
        // ── ProgressBar / İlerleme Çubuğu ──
        // progressbar: {
        //     height: '1.25rem',
        //     borderRadius: '{borderRadius.sm}',
        //     label: {
        //         fontWeight: '600',
        //         fontSize: '0.75rem',
        //     },
        // },
        // ── Skeleton / İskelet Yükleme ──
        // Loading placeholder animation.
        // Yükleme yer tutucu animasyonu.
        // skeleton: {
        //     borderRadius: '{borderRadius.sm}',
        //     animationDuration: '2s',
        // },
    },

    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    // DIRECTIVE DESIGN TOKENS
    // Direktif Tasarım Token'ları
    // ─────────────────────────────────────────────────────────────────
    // Override tokens for PrimeVue directives like Tooltip and Ripple.
    // PrimeVue direktifleri (Tooltip, Ripple vb.) için token geçersiz kılmaları.
    // ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
    directives: {
        // ── Tooltip / İpucu ──
        // Hover tooltip styling.
        // Üzerine gelme ipucu stili.
        // tooltip: {
        //     maxWidth: '12.5rem',               // Max tooltip width / Maks ipucu genişliği
        //     padding: '0.5rem 0.75rem',         // Inner padding / İç boşluk
        //     background: '{surface.800}',       // Background color / Arka plan rengi
        //     color: '{surface.0}',              // Text color / Metin rengi
        //     borderRadius: '{borderRadius.sm}', // Corner rounding / Köşe yuvarlaklığı
        //     fontSize: '0.875rem',              // Font size / Yazı tipi boyutu
        //     shadow: '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)',
        // },
        // ── Ripple / Dalga Efekti ──
        // Click ripple animation effect.
        // Tıklama dalga animasyon efekti.
        // ripple: {
        //     colorScheme: {
        //         light: {
        //             root: {
        //                 background: 'rgba(0,0,0,0.1)', // Ripple color in light mode / Açık modda dalga rengi
        //             },
        //         },
        //         dark: {
        //             root: {
        //                 background: 'rgba(255,255,255,0.3)', // Ripple color in dark mode / Koyu modda dalga rengi
        //             },
        //         },
        //     },
        // },
    },
});

export default AppPreset;
