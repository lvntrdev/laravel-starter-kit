<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bileşen Vitrini (developer-only showcase)
    |--------------------------------------------------------------------------
    | Admin/Components/* sayfalarındaki görünür metinler. PrimeVue severity
    | adları (Primary, Info, …), Tailwind renk aileleri (slate, indigo, …) ve
    | kod örnekleri API değeri olduğundan çevrilmez — sayfada literal kalır.
    */

    // Variant başlıkları — Tag ve Button vitrininde ortak kullanılır.
    'variants' => [
        'filled' => 'Dolu',
        'soft' => 'Yumuşak',
        'outlined' => 'Çerçeveli',
        'text' => 'Metin',
        'raised_rounded' => 'Yükseltilmiş · Yuvarlatılmış',
        'outlined_text' => 'Çerçeveli · Metin',
        'icon_only' => 'Yalnızca İkon',
    ],

    'sizes' => [
        'small' => 'Küçük',
        'normal' => 'Normal',
        'large' => 'Büyük',
    ],

    'docs' => 'Dokümantasyon',

    'tag' => [
        'title' => 'Etiketler',
        'subtitle' => 'Tag bileşeni — tüm varyantlar ve renkler.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> türü etiketin rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi doğrudan bir <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> olarak kullanılabilir — ör. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Tag severity="indigo" value="Secondary" /&gt;</code>.',
        'sections' => [
            'filled' => [
                'title' => 'Önem Dereceleri · Dolu',
                'desc' => 'Varsayılan dolu etiketler — severity türü etiketin rengini belirler.',
            ],
            'rounded' => [
                'title' => 'Yuvarlatılmış',
                'desc' => 'rounded özelliği hap biçimli, tam yuvarlatılmış kenarlar verir.',
            ],
            'icon' => [
                'title' => 'İkonlu',
                'desc' => 'icon özelliği ile başına anlamlı bir PrimeIcon eklenir.',
            ],
            'soft' => [
                'title' => 'Yumuşak · Tonal',
                'desc' => 'Hafif tonlu zemin, koyu renkli etiket — yoğun tablolarda daha sakin durur.',
            ],
            'outlined' => [
                'title' => 'Çerçeveli',
                'desc' => 'Şeffaf zemin, renkli halka ve etiket — düşük vurgulu bağlamlar için.',
            ],
            'removable' => [
                'title' => 'Kaldırılabilir & Boyutlar',
                'desc' => 'Kapatma düğmeli filtre çipleri; küçük · varsayılan · büyük boyut ölçeği.',
            ],
            'colors' => [
                'title' => 'Tüm Tailwind Renkleri',
                'desc' => ':count renk ailesi (mauve · olive · mist · taupe dahil) — dolu, yumuşak ve çerçeveli.',
                'badge' => ':count renk',
            ],
        ],
        'remove' => 'Kaldır',
        'states' => [
            'active' => 'Aktif',
            'approved' => 'Onaylı',
            'urgent' => 'Acil',
        ],
    ],

    'button' => [
        'title' => 'Butonlar',
        'subtitle' => 'Button bileşeni — severity, varyantlar ve Tailwind renkleri.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> türü butonun rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi (v4.2 ile gelen <strong>mauve · olive · mist · taupe</strong> dahil) doğrudan bir <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> olarak kullanılabilir — ör. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Button severity="indigo" label="Kaydet" /&gt;</code>. Dolu, çerçeveli, metin, yükseltilmiş, yuvarlatılmış ve yalnızca-ikon varyantlarının tamamıyla çalışır.',
        'sections' => [
            'severities' => [
                'title' => 'PrimeVue · Önem Dereceleri',
                'desc' => 'Yerleşik severity\'ler PrimeVue stiliyle korunur — dolu, çerçeveli ve metin.',
            ],
            'variants' => [
                'title' => 'Varyantlar',
                'desc' => 'İkon, yuvarlatılmış, yalnızca-ikon, boyutlar ve durumlar.',
            ],
        ],
        'actions' => [
            'save' => 'Kaydet',
            'next' => 'İleri',
            'delete' => 'Sil',
            'loading' => 'Yükleniyor',
            'disabled' => 'Pasif',
        ],
        'aria' => [
            'like' => 'Beğen',
            'confirm' => 'Onayla',
            'settings' => 'Ayarlar',
            'bookmark' => 'Yer imi',
        ],
    ],

    'message' => [
        'title' => 'Mesajlar',
        'subtitle' => 'Message & InlineMessage bileşenleri — tüm varyantlar ve renkler.',
        'intro' => '<code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> türü mesajın rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi (özel <strong>mauve · olive · mist · taupe</strong> dahil) doğrudan bir <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code> olarak kullanılabilir — ör. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Message severity="indigo"&gt;</code>. Varsayılan banner sakin vurgu (accent) stilidir; dolu görünüm için <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">class="p-message-fill"</code> ekleyin ya da yerleşik <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">variant="outlined" / "simple"</code> kullanın.',
        'sections' => [
            'filled' => [
                'title' => 'Önem Dereceleri · Dolu',
                'desc' => 'Dolu renkli banner — beyaz halka ikon, kalın başlık ve açıklama satırı, kapatma düğmesi.',
            ],
            'accent' => [
                'title' => 'Vurgu (Varsayılan)',
                'desc' => 'Hafif tonlu zemin, kalın renkli sol kenar ve halka ikon — satır içi bildirimler için sakin varsayılan.',
            ],
            'outlined' => [
                'title' => 'Çerçeveli',
                'desc' => 'Şeffaf zemin ve renkli çerçeve — düşük vurgulu bağlamlar için.',
            ],
            'simple' => [
                'title' => 'Sade',
                'desc' => 'Zemin ya da çerçeve yok — yalnızca renkli ikon ve metin satır içi.',
            ],
            'inline' => [
                'title' => 'InlineMessage',
                'desc' => 'Satır içi form ve alan düzeyinde geri bildirim için kompakt yumuşak çipler.',
            ],
            'colors' => [
                'title' => 'Tüm Tailwind Renkleri',
                'desc' => ':count renk ailesi (mauve · olive · mist · taupe dahil) dolu banner olarak.',
                'badge' => ':count renk',
            ],
        ],
        'items' => [
            'success' => ['title' => 'Başarılı', 'desc' => 'Kayıt başarıyla oluşturuldu ve yayınlandı.'],
            'info' => ['title' => 'Bilgi', 'desc' => 'Yeni bir sürüm güncellemesi mevcut.'],
            'warn' => ['title' => 'Uyarı', 'desc' => 'Oturumunuz 5 dakika içinde sona erecek.'],
            'danger' => ['title' => 'Hata', 'desc' => 'Kaydetme sırasında bir hata oluştu, tekrar deneyin.'],
            'secondary' => ['title' => 'İkincil', 'desc' => 'Değişiklikler taslak olarak kaydedildi.'],
            'contrast' => ['title' => 'Kontrast', 'desc' => 'Yüksek kontrastlı bir bildirim mesajı.'],
        ],
    ],

];
