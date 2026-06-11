<?php

return [
    'entity' => 'İçerik Dili',
    'title' => 'İçerik Dilleri',
    'subtitle' => 'Çok dilli içerik alanlarının çevrildiği diller — yönetim arayüzü dilinden ayrıdır.',

    'create' => 'Dil Ekle',
    'edit' => 'Dili Düzenle',
    'delete_confirm' => '":name" içerik dilini silmek istediğinizden emin misiniz? Bu dile ait mevcut çeviriler artık gösterilmeyecektir.',

    'active' => 'Aktif',
    'inactive' => 'Pasif',
    'default' => 'Varsayılan',

    'directions' => [
        'ltr' => 'Soldan sağa (LTR)',
        'rtl' => 'Sağdan sola (RTL)',
    ],

    'fields' => [
        'code' => 'Kod',
        'code_hint' => 'Küçük harf yerel ayar kodu, örn. "en", "en-us", "tr".',
        'name' => 'Ad',
        'native_name' => 'Yerel Ad',
        'direction' => 'Metin Yönü',
        'flag' => 'Bayrak',
        'flag_hint' => 'Dilin yanında gösterilecek isteğe bağlı bayrak emojisi veya simgesi.',
        'is_active' => 'Aktif',
        'is_active_hint' => 'Yalnızca aktif diller çok dilli içerik alanlarında sekme olarak görünür.',
        'is_default' => 'Varsayılan',
        'is_default_hint' => 'Yedek içerik dili. Tam olarak bir dil varsayılan olur.',
        'fallback_code' => 'Yedek Dil',
        'fallback_none' => 'Yok',
        'fallback_hint' => 'İçerik eksik olduğunda dönülecek dil (gelecekteki kullanım için saklanır).',
        'sort_order' => 'Sıralama',
    ],

    'errors' => [
        'delete_default' => 'Varsayılan içerik dili silinemez. Önce başka bir dili varsayılan yapın.',
        'last_default' => 'En az bir aktif varsayılan içerik dili kalmalıdır.',
        'fallback_self' => 'Bir dil kendisini yedek olarak kullanamaz.',
    ],
];
