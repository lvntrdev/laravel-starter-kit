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

    // Sekmeli showcase sayfasının (SkComponents/Show) genel metinleri.
    'subtitle' => 'UI kit örnekleri — kit\'in PrimeVue + SK bileşenleri tüm varyantlarıyla.',

    'tag' => [
        'title' => 'Etiketler',
        'subtitle' => 'Tag bileşeni — tüm varyantlar ve renkler.',
        'intro' => 'severity türü etiketin rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi doğrudan bir severity olarak kullanılabilir — ör. severity="indigo".',
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
        'intro' => 'severity türü butonun rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi (özel mauve · olive · mist · taupe dahil) doğrudan bir severity olarak kullanılabilir — ör. severity="indigo". Dolu, çerçeveli, metin, yükseltilmiş, yuvarlatılmış ve yalnızca-ikon varyantlarının tamamıyla çalışır.',
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
        'intro' => 'severity türü mesajın rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi (özel mauve · olive · mist · taupe dahil) doğrudan bir severity olarak kullanılabilir — ör. severity="indigo". Varsayılan banner sakin vurgu (accent) stilidir; dolu görünüm için class="p-message-fill" ekleyin ya da variant="outlined" / "simple" kullanın.',
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

    'toast' => [
        'title' => 'Toast',
        'subtitle' => 'Toast bildirimleri — tüm önem dereceleri, varyantlar ve renkler.',
        'intro' => 'severity toast türünü belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi (özel mauve · olive · mist · taupe dahil) doğrudan bir severity olarak kullanılabilir — ör. toast.add({ severity: "indigo" }). Varsayılan görünüm hafif tonlu vurgu kartıdır; dolu görünüm için styleClass: "sk-toast-solid", çerçeveli için styleClass: "sk-toast-outlined" geçin.',
        'sections' => [
            'severity' => [
                'title' => 'Önem Derecesi',
                'desc' => 'severity toast türünü tanımlar — hafif tonlu varsayılan varyant, dairesel halka ikon ve ilerleme çubuğu ile.',
            ],
            'variants' => [
                'title' => 'Çerçeveli & Dolu',
                'desc' => 'Çerçeveli: düz zemin, renkli halka ikon. Dolu: tümüyle renkli kart, beyaz halka ikon ve metin.',
            ],
            'sticky' => [
                'title' => 'Eylemli (Sticky)',
                'desc' => 'Kapanmadan beklemesi gereken, hap biçimli onay düğmeleri taşıyan toast — silme onayı ya da tek satırlık bir eylem.',
            ],
            'colors' => [
                'title' => 'Tüm Tailwind Renkleri',
                'desc' => ':count renk ailesi (mauve · olive · mist · taupe dahil) toast olarak — 600 tonu halka ikonu ve vurguyu boyar.',
                'badge' => ':count renk',
            ],
            'live' => [
                'title' => 'Canlı Önizleme',
                'desc' => 'Gerçek toast bildirimlerini tetikleyin — bu düğmeler çalışma anındaki ToastComponent\'i kullanır.',
                'update' => 'Eylemli toast',
                'delete' => 'Silme onayı',
            ],
        ],
        'items' => [
            'success' => ['summary' => 'Başarılı', 'detail' => 'Kayıt başarıyla oluşturuldu.'],
            'info' => ['summary' => 'Bilgi', 'detail' => 'Profiliniz güncellendi.'],
            'warn' => ['summary' => 'Uyarı', 'detail' => 'Disk alanınızın %90\'ı doldu.'],
            'error' => ['summary' => 'Hata', 'detail' => 'İşlem tamamlanamadı, tekrar deneyin.'],
            'secondary' => ['summary' => 'İkincil', 'detail' => 'Değişiklikler taslak olarak kaydedildi.'],
            'contrast' => ['summary' => 'Kontrast', 'detail' => 'Yüksek kontrastlı bir bildirim.'],
        ],
        'sticky' => [
            'delete' => [
                'summary' => 'Kaydı sil?',
                'detail' => 'Bu işlem geri alınamaz.',
                'confirm' => 'Sil',
                'cancel' => 'Vazgeç',
            ],
            'update' => [
                'summary' => 'Yeni sürüm hazır',
                'detail' => 'Uygulamayı yeniden başlatın.',
                'confirm' => 'Güncelle',
                'cancel' => 'Sonra',
            ],
            'retry' => [
                'summary' => 'Bağlantı kesildi.',
                'retry' => 'Yeniden dene',
            ],
        ],
    ],

    'form' => [
        'title' => 'FormBuilder',
        'subtitle' => 'Bildirimsel formlar — tüm FB.* alan tipleri ve kart, aside, ayraçlı layout düzenleri.',
        'intro' => 'Formlar FB builder ile bildirimsel olarak yapılandırılır ve <SkForm> ile render edilir. Aşağıdaki her örnek v-model modunda canlı bir <SkForm>\'dur (submit yok) — başlık, gösterilen FB.* factory\'sini veya layout metodunu belirtir.',
        'docs' => 'FormBuilder Dokümanı',
        'field_count' => ':count alan',
        'sections' => [
            'text' => [
                'title' => 'Metin / sayı girişleri',
                'desc' => 'inputText, inputMask, inputNumber, password, inputOtp, datePicker, textarea ve editor.',
            ],
            'choice' => [
                'title' => 'Seçim alanları',
                'desc' => 'select, multiselect, radio, checkboxGroup ve selectButton — her biri options ya da definition key taşır.',
            ],
            'boolean' => [
                'title' => 'Boolean / toggle',
                'desc' => 'checkbox, toggleSwitch ve toggleButton — satır içi etiketli kontroller.',
            ],
            'special' => [
                'title' => 'Özel alanlar',
                'desc' => 'fileUpload (sürükle-bırak) ve colorSelector (renk ailesi + ton).',
            ],
            'i18n' => [
                'title' => 'Çok dilli',
                'desc' => 'translatableText ve translatableTextarea — aktif dil başına bir değer.',
            ],
            'layouts' => [
                'title' => 'Layout düzenleri',
                'desc' => 'Aynı alanlar gerçek düzenlerde — aksiyon çubuklu kart, aside bölüm, ayraçlı yatay satırlar ve satır içi filtre çubuğu.',
            ],
        ],
        'layouts' => [
            'card' => [
                'title' => 'Form kartı + aksiyon çubuğu',
                'desc' => 'cardTitle / cardSubtitle ile alt aksiyon çubuğu (submit modu).',
            ],
            'aside' => [
                'title' => 'Aside bölüm',
                'desc' => 'FB.section().aside() — başlık solda, alanlar sağda. Ayarlar sayfası deseni.',
            ],
            'divided' => [
                'title' => 'Ayraçlı yatay satırlar',
                'desc' => "FB.form().layout('horizontal').dividers() — etiketler solda, satır arası ince ayraç.",
            ],
            'filter' => [
                'title' => 'Satır içi filtre çubuğu',
                'desc' => 'Liste filtreleme için kompakt tek satır form.',
            ],
        ],
    ],

];
