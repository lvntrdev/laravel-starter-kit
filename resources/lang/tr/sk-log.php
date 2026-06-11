<?php

return [
    'title' => 'Log Dosyaları',
    'subtitle' => 'Laravel log dosyalarını oku ve yönet',
    'filename' => 'Dosya Adı',
    'channel' => 'Kanal',
    'channel_daily' => 'günlük',
    'channel_single' => 'tekil',
    'channel_other' => 'diğer',
    'size' => 'Boyut',
    'modified' => 'Değiştirildi',
    'active' => 'Aktif',
    'active_yes' => 'Aktif',
    'back_to_list' => 'Günlüklere dön',
    'all' => 'Tümü',

    // Liste görünümü
    'search_files' => 'Dosya adı ara',
    'channel_all' => 'Tümü',
    'clear_filters' => 'Filtreleri Temizle',
    'no_files_title' => 'Eşleşen günlük dosyası yok',
    'no_files_sub' => 'Arama veya kanal süzgecinizi gözden geçirin.',
    'per_page' => 'Sayfa başına',
    'showing_files_range' => ':total kayıttan :from–:to arası gösteriliyor',

    // Filtreler
    'level' => 'Seviye',
    'from' => 'Başlangıç',
    'to' => 'Bitiş',
    'search_messages' => 'Mesajlarda ara',
    'keyword_placeholder' => 'Anahtar kelime, sınıf, dosya…',
    'all_levels' => 'Tüm seviyeler',
    'apply' => 'Uygula',
    'reset' => 'Sıfırla',
    'load_more' => 'Daha fazla yükle',
    'expand_all' => 'Tümünü aç',
    'collapse_all' => 'Tümünü kapat',
    'entry_count' => ':count kayıt',
    'stacktrace' => 'yığın izi',
    'no_entries' => 'Eşleşen kayıt yok',
    'no_entries_sub' => 'Seviye, zaman aralığı veya arama ölçütünü gözden geçirin.',
    'showing_n_entries' => ':count kayıt gösteriliyor',
    'eof' => 'Dosya sonu',

    // Silme
    'delete_selected' => 'Seçiliyi sil',
    'delete_confirm' => '":name" log dosyası silinsin mi? Geri alınamaz.',
    'deleted_count' => ':count log dosyası silindi.',
    'failed_count' => ':count log dosyası silinemedi:',

    // Hata sebepleri (DeleteLogFilesAction reason kodlarıyla eşleşmeli)
    'reason_invalid_filename' => 'geçersiz dosya adı',
    'reason_not_found' => 'bulunamadı',
    'reason_active_file_protected' => 'aktif dosya korumalı',
    'reason_delete_failed' => 'silme başarısız',

    // Sunucu hata anahtarları (PHP exception'larından referans verilir)
    'invalid_filename' => 'Geçersiz log dosya adı.',
    'file_not_found' => 'Log dosyası bulunamadı.',
    'active_file_protected' => 'Aktif log dosyaları silinemez.',
    'read_failed' => 'Log dosyası okunamadı.',
];
