<?php

return [
    'title' => 'Ayarlar',
    'subtitle' => 'Uygulama yapılandırmasını yönetin.',

    'flash' => [
        'general' => 'Genel ayarlar güncellendi.',
        'auth' => 'Güvenlik ayarları güncellendi.',
        'mail' => 'E-posta ayarları güncellendi.',
        'storage' => 'Depolama ayarları güncellendi.',
        'file_manager' => 'Dosya yöneticisi ayarları güncellendi.',
        'turnstile' => 'Turnstile ayarları güncellendi.',
        'postman' => 'Postman ayarları güncellendi.',
        'apidog' => 'Apidog ayarları güncellendi.',
        'logo_uploaded' => 'Logo yüklendi.',
        'test_mail_sent' => 'Test e-postası başarıyla gönderildi.',
        'test_mail_failed' => 'Test e-postası gönderilemedi. Ayrıntılar için sunucu günlüklerini kontrol edin.',
    ],

    'tabs' => [
        'general' => 'Genel',
        'auth' => 'Güvenlik Ayarları',
        'mail' => 'E-posta',
        'storage' => 'Depolama',
        'file_manager' => 'Dosya Yöneticisi',
        'api_integrations' => 'API Entegrasyonları',
        'api_clients' => 'API İstemcileri',
        'api_tokens' => 'API Tokenleri',
        'system_health' => 'Sistem Sağlığı',
    ],

    'tab_descriptions' => [
        'general' => 'Uygulama adı, dil ve logo',
        'auth' => 'Kayıt, 2FA, e-posta doğrulama ve CAPTCHA',
        'mail' => 'SMTP ve gönderen ayarları',
        'storage' => 'S3, Spaces ve yerel disk',
        'file_manager' => 'Yükleme boyutu ve dosya türleri',
        'api_integrations' => 'Postman ve Apidog entegrasyonu',
        'api_clients' => 'OAuth istemcileri yönetimi',
        'api_tokens' => 'Kişisel erişim token yönetimi',
        'system_health' => 'Sistem doktoru kontrolleri',
    ],

    'general' => [
        'title' => 'Genel Ayarlar',
        'subtitle' => 'Uygulama adı, bölgesel tercihler ve temel kimlik bilgileri.',
        'identity_title' => 'Site Kimliği',
        'identity_subtitle' => 'Sitenizin temel kimlik bilgileri arama motorlarında ve sekmelerde gözükür.',
        'regional_title' => 'Bölgesel Ayarlar',
        'regional_subtitle' => 'Saat dilimi, dil ve para birimi kullanıcılara gösterilen verileri biçimlendirir.',
        'welcome_title' => 'Karşılama Mesajı',
        'welcome_subtitle' => 'Yönetim panosunda gösterilir. Temel biçimlendirme ve görselleri destekler.',
        'default_language_hint' => 'Varsayılan locale olarak kullanılır; aktif dillerden biri olmalıdır.',
        'languages_hint' => 'Uygulamanızın desteklediği dilleri seçin.',
        'logo' => 'Uygulama Logosu',
        'logo_hint' => 'Yan menüde ve giriş sayfasında gösterilecek bir logo yükleyin.',
        'logo_upload' => 'Logo Yükle',
        'logo_remove' => 'Kaldır',
        'logo_remove_confirm' => 'Uygulama logosunu kaldırmak istediğinizden emin misiniz?',
        'welcome_message_placeholder' => 'Kısa bir hoş geldin mesajı yazın…',
        'welcome_message_hint' => 'Yönetim panelinin ana sayfasında gösterilir. Temel biçimlendirme ve görsel desteği vardır.',
    ],

    'security' => [
        'auth_section_title' => 'Kimlik Doğrulama',
        'turnstile_section_title' => 'Cloudflare Turnstile',
    ],

    'auth' => [
        'title' => 'Kimlik Doğrulama Ayarları',
        'subtitle' => 'Kimlik doğrulama özelliklerini etkinleştirin veya devre dışı bırakın.',
        'registration_hint' => 'Yeni kullanıcıların hesap açmasına izin verin.',
        'email_verification_hint' => 'Kullanıcıların kayıt sonrası e-posta adreslerini doğrulamasını zorunlu kılın.',
        'two_factor_hint' => 'Kullanıcıların ek güvenlik için iki adımlı doğrulamayı etkinleştirmesine izin verin.',
        'two_factor_disable_title' => 'İki adımlı doğrulama kapatılsın mı?',
        'two_factor_disable_warning' => 'Bu ayar kapatıldığında tüm kullanıcılar için ek giriş kontrolü kaldırılır. Mevcut 2FA sırları temizlenir; özelliği tekrar açarsan ilgili kullanıcıların yeniden kurulum yapması gerekir.',
        'password_reset_hint' => 'Kullanıcıların e-posta bağlantısı ile parolalarını sıfırlamasına izin verin.',
    ],

    'mail' => [
        'title' => 'E-posta Ayarları',
        'subtitle' => 'Giden e-posta ayarlarını yapılandırın.',
        'encryption_none' => 'Yok',
        'test_title' => 'Test E-postası',
        'test_subtitle' => 'E-posta yapılandırmanızı doğrulamak için bir test e-postası gönderin.',
        'test_send' => 'Test Gönder',
    ],

    'storage' => [
        'title' => 'Medya Depolama',
        'subtitle' => 'Yüklenen medya dosyalarının nerede saklanacağını seçin.',
        'local' => 'Yerel',
        'spaces' => 'DigitalOcean Spaces',
        's3' => 'Amazon S3',
        'spaces_title' => 'DigitalOcean Spaces',
        'spaces_subtitle' => 'DigitalOcean Spaces kimlik bilgilerini ve ayarlarını yapılandırın.',
        's3_title' => 'Amazon S3',
        's3_subtitle' => 'AWS S3 kimlik bilgilerini ve ayarlarını yapılandırın.',
        'usage_card_title' => 'Depolama Kullanımı',
        'usage_label' => ':used / :total kullanıldı (%:percent)',
        'unlimited' => 'sınırsız',
    ],

    'file_manager' => [
        'title' => 'Dosya Yöneticisi Ayarları',
        'subtitle' => 'Yüklenebilir dosya boyutunu ve türlerini yapılandırın.',
        'media_section_title' => 'Medya',
        'archive_section_title' => 'Arşiv',
        'video_label' => 'Video Yüklemeleri',
        'audio_label' => 'Ses Yüklemeleri',
        'video_hint' => 'MP4, WebM, MOV, MKV, AVI ve OGG video yüklemelerine izin ver',
        'audio_hint' => 'MP3, WAV, OGG ve WebM ses dosyalarına izin ver',
        'storage_quota' => [
            'label' => 'Depolama Kotası (GB)',
            'help' => 'Tüm context\'leri ve çöp kutusunu kapsayan tek toplam kota.',
        ],
        'mime_categories' => [
            'images' => 'Görseller',
            'documents' => 'Dokümanlar',
            'archive' => 'Arşiv',
        ],
    ],

    'turnstile' => [
        'title' => 'Cloudflare Turnstile',
        'subtitle' => 'Giriş, kayıt ve şifre sıfırlama formlarını CAPTCHA ile koruyun.',
        'enabled_hint' => 'Turnstile doğrulamayı aktif et',
        'site_key_label' => 'Site Anahtarı',
        'secret_key_label' => 'Gizli Anahtar',
    ],

    'postman' => [
        'title' => 'Postman Entegrasyonu',
        'subtitle' => "API Rotaları sayfasından OpenAPI belgesini Postman'e gönderebilmek için yapılandırın.",
        'api_key_label' => 'API Anahtarı',
        'api_key_hint' => "postman.co → Settings → API Keys'ten üretilen kişisel anahtar (PMAK- ile başlar).",
        'workspace_id_label' => 'Workspace ID',
        'workspace_id_hint' => "Workspace URL'indeki UUID kısmı.",
        'collection_id_label' => 'Mevcut Postman Koleksiyon UID',
        'collection_id_hint' => 'Her Postman senkronizasyonundan sonra otomatik güncellenir. Manuel düzenlemeye gerek yok.',
    ],

    'apidog' => [
        'title' => 'Apidog Entegrasyonu',
        'subtitle' => 'OpenAPI belgesini mevcut bir Apidog projesine gönderir; eşleşen endpointler üzerine yazılır.',
        'access_token_label' => 'Access Token',
        'access_token_hint' => 'apidog.com → Account Settings → API Access Token üzerinden üretilen kişisel token.',
        'project_id_label' => 'Proje ID',
        'project_id_hint' => 'Apidog proje URL\'inde yer alan sayısal proje ID (…/project/<id>).',
    ],
];
