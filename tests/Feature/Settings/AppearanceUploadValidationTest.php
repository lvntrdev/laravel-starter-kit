<?php

use App\Http\Requests\Admin\Settings\UploadAppearanceLogoRequest;
use App\Http\Requests\Admin\Settings\UploadFaviconRequest;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| Appearance uploads — FormRequest validation + authorize (Görünüm · güvenlik)
|--------------------------------------------------------------------------
|
| Logo/favicon yükleme doğrulaması inline `validate()` yerine FormRequest'e
| taşındı; bu, SVG-reddi kırmızı çizgisini birim-test edilebilir kılar
| (regresyonla svg allowlist'e geri eklenirse test kırmızı yanar) ve
| route middleware'ine ek olarak `authorize()` permission katmanı ekler.
|
| Stub FormRequest'ler App\ namespace'inde autoload edilmez — dosyalar
| doğrudan require edilir (ContentLanguageTest / AuthSettingsTest deseni).
|
*/

$stubs = dirname(__DIR__, 3).'/stubs';
require_once $stubs.'/app/Http/Requests/Admin/Settings/UploadAppearanceLogoRequest.php';
require_once $stubs.'/app/Http/Requests/Admin/Settings/UploadFaviconRequest.php';

/**
 * Minimal authorizable actor whose `can()` resolves against an explicit
 * granted-ability list via a test-defined Gate.
 *
 * @param  list<string>  $granted
 */
function appearanceUploadActor(array $granted): Authorizable
{
    return new class($granted) implements Authorizable
    {
        use AuthorizableTrait;

        /** @param list<string> $granted */
        public function __construct(public array $granted) {}
    };
}

beforeEach(function (): void {
    Gate::define('settings.update', fn ($user) => in_array('settings.update', $user->granted ?? [], true));
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. Logo upload — mime allowlist (SVG red line)
// ──────────────────────────────────────────────────────────────────────────────

it('rejects an SVG logo upload (script-execution red line)', function (): void {
    $rules = (new UploadAppearanceLogoRequest)->rules();

    $svg = UploadedFile::fake()->createWithContent(
        'logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
    );

    $validator = Validator::make(['logo' => $svg], $rules);

    expect($validator->fails())->toBeTrue();
});

it('accepts a PNG logo upload within the size/dimension limits', function (): void {
    $rules = (new UploadAppearanceLogoRequest)->rules();

    $png = UploadedFile::fake()->image('logo.png', 240, 60);

    $validator = Validator::make(['logo' => $png], $rules);

    expect($validator->passes())->toBeTrue();
});

it('rejects a logo whose dimensions exceed the cap', function (): void {
    $rules = (new UploadAppearanceLogoRequest)->rules();

    $huge = UploadedFile::fake()->image('logo.png', 5000, 5000);

    $validator = Validator::make(['logo' => $huge], $rules);

    expect($validator->fails())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Favicon upload — png/ico only (no SVG, no jpeg/webp)
// ──────────────────────────────────────────────────────────────────────────────

it('rejects an SVG favicon upload', function (): void {
    $rules = (new UploadFaviconRequest)->rules();

    $svg = UploadedFile::fake()->createWithContent('favicon.svg', '<svg/>');

    $validator = Validator::make(['favicon' => $svg], $rules);

    expect($validator->fails())->toBeTrue();
});

it('rejects a JPEG favicon (favicons are png/ico only)', function (): void {
    $rules = (new UploadFaviconRequest)->rules();

    $jpg = UploadedFile::fake()->image('favicon.jpg', 32, 32);

    $validator = Validator::make(['favicon' => $jpg], $rules);

    expect($validator->fails())->toBeTrue();
});

it('accepts a PNG favicon', function (): void {
    $rules = (new UploadFaviconRequest)->rules();

    $png = UploadedFile::fake()->image('favicon.png', 32, 32);

    $validator = Validator::make(['favicon' => $png], $rules);

    expect($validator->passes())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. authorize() — settings.update gate (defense-in-depth over route middleware)
// ──────────────────────────────────────────────────────────────────────────────

it('logo upload authorize() denies an actor without settings.update', function (): void {
    $request = UploadAppearanceLogoRequest::create('/settings/appearance/logo/light', 'POST');
    $request->setUserResolver(fn () => appearanceUploadActor(['settings.read']));

    expect($request->authorize())->toBeFalse();
});

it('logo upload authorize() allows an actor with settings.update', function (): void {
    $request = UploadAppearanceLogoRequest::create('/settings/appearance/logo/light', 'POST');
    $request->setUserResolver(fn () => appearanceUploadActor(['settings.update']));

    expect($request->authorize())->toBeTrue();
});

it('favicon upload authorize() denies a guest', function (): void {
    $request = UploadFaviconRequest::create('/settings/appearance/favicon', 'POST');
    $request->setUserResolver(fn () => null);

    expect($request->authorize())->toBeFalse();
});
