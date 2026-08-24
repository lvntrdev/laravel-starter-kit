<?php

use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\Admin\ContentLanguageController;

/*
|--------------------------------------------------------------------------
| Content Language Routes
|--------------------------------------------------------------------------
|
| Bu rotalar Settings sayfasındaki "İçerik Dilleri" tab'ından kullanılır;
| menüde bağımsız bir sayfa olarak görünmez. İçerik dilleri, translatable
| içerik alanlarının (TranslatableInput) gezdiği dilleri belirler — admin UI
| dilinden (config('app.languages')) ayrı bir kavramdır.
|
| Guard: web (admin paneli — session tabanlı kimlik doğrulama).
|
| Yetkilendirme: ayrı bir "content-languages" izni YOKTUR — içerik dilleri
| bir ayar olduğundan Settings'in kendi izinleriyle korunur (settings-route.php
| deseninin aynısı):
|   - görüntüleme (dt, fetch)        → check.permission:settings.read
|   - yazma (add, save, remove)      → check.permission:settings.update
|
| Rota adlarının son segmentleri (dt, fetch, add, save, remove) kasıtlı olarak
| CheckResourcePermission::ACTION_ABILITY_MAP dışındadır: bunlar kaynağa göre
| anlam değiştiren fiiller, global bir eşleme aynı sözcükle biten alakasız bir
| tüketici rotasını (orders.save) sessizce kapatırdı.
|
| Bunun yerine bu beş ad, tam adlarıyla
| CheckResourcePermission::PACKAGE_ROUTE_PERMISSIONS içinde sabitlenmiştir —
| yani web.php'nin parametresiz `check.permission` grubu artık bu rotalardan da
| yukarıdaki explicit izinlerin aynısını türetir, pass-through olmaz. Asıl
| korumayı yine yukarıdaki explicit izinler yapar; paket haritası yalnızca
| parametresiz geçişin çözülemeyen dala düşmesini engeller
| (settings.update.general / upload.logo rotalarıyla birebir aynı mekanizma).
|
*/

Route::prefix('settings/content-languages')
    ->name('settings.contentLanguages.')
    ->controller(ContentLanguageController::class)
    ->group(function () {
        Route::get('dt', 'dtApi')->name('dt')->middleware('check.permission:settings.read');
        Route::get('{contentLanguage}/data', 'data')->name('fetch')->middleware('check.permission:settings.read');
        Route::post('/', 'store')->name('add')->middleware('check.permission:settings.update');
        Route::put('{contentLanguage}', 'update')->name('save')->middleware('check.permission:settings.update');
        Route::delete('{contentLanguage}', 'destroy')->name('remove')->middleware('check.permission:settings.update');
    });
