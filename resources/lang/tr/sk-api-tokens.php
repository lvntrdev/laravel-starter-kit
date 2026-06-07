<?php

return [
    'title' => 'API Tokenleri',
    'subtitle' => 'Kişisel Erişim Token Yönetimi',
    'token' => 'API Token',
    'create' => 'Token Oluştur',
    'revoke' => 'Token\'ı İptal Et',
    'revoke_confirm' => 'Bu token\'ı iptal etmek istediğinizden emin misiniz? Token hemen geçersiz hale gelecektir.',

    'fields' => [
        'name' => 'Token Adı',
        'scopes' => 'Kapsamlar',
        'user' => 'Kullanıcı',
        'expires_at' => 'Son Kullanma Tarihi',
    ],

    'token_modal' => [
        'title' => 'Kişisel Erişim Token',
        'description' => 'Bu token yalnızca şu an görüntülenebilir. Kopyalayın ve güvenli bir yerde saklayın. Bu iletişim kutusu kapatıldıktan sonra bir daha görüntülenemez.',
        'copy' => 'Token\'ı Kopyala',
        'copied' => 'Kopyalandı!',
        'confirm' => 'Token\'ı kaydettim',
    ],

    'created' => 'API token başarıyla oluşturuldu.',
    'revoked' => 'API token iptal edildi.',
];
