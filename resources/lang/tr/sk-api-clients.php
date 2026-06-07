<?php

return [
    'title' => 'API İstemcileri',
    'subtitle' => 'OAuth2 İstemci Yönetimi',
    'client' => 'API İstemcisi',
    'create' => 'İstemci Oluştur',
    'edit' => 'İstemciyi Düzenle',
    'revoke' => 'İstemciyi İptal Et',
    'revoke_confirm' => '":name" istemcisini iptal etmek istediğinizden emin misiniz? Bağlı tüm token\'lar da iptal edilecektir.',

    'fields' => [
        'name' => 'İstemci Adı',
        'grant_type' => 'İzin Türü',
        'redirect_uris' => 'Yönlendirme Adresleri',
        'redirect_uris_hint' => 'Her adresi girdikten sonra Enter veya virgül tuşuna basın. Örnek: https://app.example.com/callback',
        'scopes' => 'İzin Verilen Kapsamlar',
        'confidential' => 'Gizli İstemci',
        'client_id' => 'İstemci ID',
        'client_secret' => 'İstemci Gizli Anahtarı',
    ],

    'grant_types' => [
        'authorization_code' => 'Yetkilendirme Kodu',
        'client_credentials' => 'İstemci Kimlik Bilgileri',
        'personal_access' => 'Kişisel Erişim',
    ],

    'secret_modal' => [
        'title' => 'İstemci Gizli Anahtarı',
        'description' => 'Bu gizli anahtar yalnızca şu an görüntülenebilir. Kopyalayın ve güvenli bir yerde saklayın. Bu iletişim kutusu kapatıldıktan sonra bir daha görüntülenemez.',
        'copy' => 'Anahtarı Kopyala',
        'copied' => 'Kopyalandı!',
        'confirm' => 'Anahtarı kaydettim',
    ],

    'created' => 'API istemcisi başarıyla oluşturuldu.',
    'updated' => 'API istemcisi başarıyla güncellendi.',
    'revoked' => 'API istemcisi iptal edildi.',

    'errors' => [
        'fetch_failed_summary' => 'Yükleme Hatası',
        'fetch_failed' => 'İstemci bilgileri yüklenemedi. Lütfen tekrar deneyin.',
    ],

    'validation' => [
        'grant_type_invalid' => 'Seçilen izin türü desteklenmiyor.',
        'redirect_uri_https_required' => 'Yönlendirme adresleri HTTPS kullanmalıdır. Yalnızca localhost (127.0.0.1, ::1) HTTP ile kullanılabilir.',
    ],
];
