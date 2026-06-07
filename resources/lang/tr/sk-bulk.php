<?php

return [
    // FormRequest doğrulama mesajları
    'ids_required' => 'En az bir kayıt seçilmelidir.',
    'ids_min' => 'En az bir kayıt seçilmelidir.',
    'ids_max' => 'Tek işlemde en fazla 500 kayıt işlenebilir.',
    'action_required' => 'Toplu işlem türü belirtilmelidir.',

    // Dispatcher mesajları
    'unsupported_action' => 'Desteklenmeyen toplu işlem: :action.',
    'no_authorized_items' => 'Seçili kayıtların hiçbirinde bu işlemi gerçekleştirme yetkiniz yok.',

    // Sonuç mesajı
    'result' => ':processed kayıt işlendi, :skipped atlandı, :failed başarısız oldu.',
];
