<?php

use App\Domain\ContentLanguage\Actions\CreateContentLanguageAction;
use App\Domain\ContentLanguage\Actions\DeleteContentLanguageAction;
use App\Domain\ContentLanguage\Actions\UpdateContentLanguageAction;
use App\Domain\ContentLanguage\DTOs\ContentLanguageDTO;
use App\Http\Requests\Admin\ContentLanguage\StoreContentLanguageRequest;
use App\Http\Requests\Admin\ContentLanguage\UpdateContentLanguageRequest;
use App\Models\ContentLanguage;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Lvntr\StarterKit\Exceptions\ApiException;

/*
|--------------------------------------------------------------------------
| Content Languages — domain + invariant + permission gating (Task 8)
|--------------------------------------------------------------------------
|
| İçerik dilleri, translatable içerik alanlarının (TranslatableInput) gezdiği
| dilleri belirler — admin UI dilinden (config('app.languages')) ayrı bir
| kavramdır. Bu test, plan Task 1-4'te kurulan domain/HTTP katmanının
| sözleşmesini doğrular:
|
|   1. CRUD — Create/Update/Delete Action'lar DTO üzerinden çalışır.
|   2. Tek-default invariantı — aynı anda yalnız bir is_default = true; yeni
|      default set edilince diğerleri demote edilir.
|   3. Son-default guard'ı — tek aktif default demote/deaktive edilemez;
|      default dil silinemez (ApiException 422).
|   4. is_active filtresi — datatable filterable allow-list'i.
|   5. unique(code) + exists(fallback_code) — FormRequest validation kuralları.
|   6. Permission gating — yetkisiz aktör FormRequest authorize() + controller
|      abort_unless guard'larında reddedilir; yetkili aktör geçer.
|
| Stub sınıfları App\ namespace'inde autoload edilmez — dosyalar doğrudan
| yüklenir (AuthSettingsTest / PasswordPolicyTest deseni). content_languages
| tablosu beforeEach'te inline kurulur (BulkSelectionQueryTest deseni) —
| paylaşılan DatabaseTestCase şemasına dokunulmaz.
|
*/

$stubs = dirname(__DIR__, 3).'/stubs';

require_once $stubs.'/app/Exceptions/ApiException.php';

if (! class_exists(ContentLanguage::class)) {
    require_once $stubs.'/app/Models/ContentLanguage.php';
}

require_once $stubs.'/app/Domain/ContentLanguage/DTOs/ContentLanguageDTO.php';
require_once $stubs.'/app/Domain/ContentLanguage/Actions/CreateContentLanguageAction.php';
require_once $stubs.'/app/Domain/ContentLanguage/Actions/UpdateContentLanguageAction.php';
require_once $stubs.'/app/Domain/ContentLanguage/Actions/DeleteContentLanguageAction.php';
require_once $stubs.'/app/Http/Requests/Admin/ContentLanguage/StoreContentLanguageRequest.php';
require_once $stubs.'/app/Http/Requests/Admin/ContentLanguage/UpdateContentLanguageRequest.php';

beforeEach(function (): void {
    Schema::create('content_languages', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->string('native_name');
        $table->string('direction')->default('ltr');
        $table->string('flag')->nullable();
        $table->boolean('is_active')->default(true)->index();
        $table->boolean('is_default')->default(false);
        $table->string('fallback_code')->nullable();
        $table->integer('sort_order')->default(0)->index();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('content_languages');
});

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

/**
 * @param  array<string, mixed>  $overrides
 */
function contentLanguageDto(array $overrides = []): ContentLanguageDTO
{
    return ContentLanguageDTO::fromArray($overrides + [
        'code' => 'en',
        'name' => 'English',
        'native_name' => 'English',
        'direction' => 'ltr',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);
}

/**
 * Minimal Authorizable aktör; can() Gate üzerinden çözülür. Verilen
 * yetenekler izinli, gerisi reddedilir (rolsüz direct-permission aktör gibi).
 *
 * @param  string[]  $abilities
 */
function contentLanguageActor(array $abilities): Authorizable
{
    return new class($abilities) extends Model implements Authorizable
    {
        use AuthorizableTrait;

        protected $table = 'users';

        protected $guarded = [];

        public $timestamps = false;

        /** @var string[] */
        public array $grantedAbilities;

        /** @param string[] $abilities */
        public function __construct(array $abilities = [])
        {
            parent::__construct();
            $this->grantedAbilities = $abilities;
        }
    };
}

/**
 * Gate'i, aktörün grantedAbilities listesine göre kuran kurulum.
 * İçerik dilleri ayrı bir izin taşımaz — Settings'in kendi izinleriyle korunur
 * (görüntüleme settings.read, yazma settings.update); bu yetenekler aktörün
 * taşıdığı listeye karşı denetlenir.
 */
function bindContentLanguageGate(): void
{
    foreach (['read', 'update'] as $action) {
        Gate::define("settings.{$action}", function ($user) use ($action): bool {
            return in_array("settings.{$action}", $user->grantedAbilities ?? [], true);
        });
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. CRUD
// ──────────────────────────────────────────────────────────────────────────────

it('creates a content language from a DTO', function (): void {
    $language = app(CreateContentLanguageAction::class)
        ->execute(contentLanguageDto(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'sort_order' => 3]));

    expect($language->exists)->toBeTrue()
        ->and($language->code)->toBe('fr')
        ->and($language->native_name)->toBe('Français')
        ->and($language->is_active)->toBeTrue()
        ->and($language->is_default)->toBeFalse()
        ->and($language->sort_order)->toBe(3);

    expect(ContentLanguage::query()->where('code', 'fr')->exists())->toBeTrue();
});

it('updates a content language through the action', function (): void {
    $language = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch']));

    $updated = app(UpdateContentLanguageAction::class)->execute(
        $language,
        contentLanguageDto(['code' => 'de', 'name' => 'German (DE)', 'native_name' => 'Deutsch', 'sort_order' => 7]),
    );

    expect($updated->name)->toBe('German (DE)')
        ->and($updated->sort_order)->toBe(7)
        ->and(ContentLanguage::query()->find($language->id)->name)->toBe('German (DE)');
});

it('deletes a non-default content language', function (): void {
    $language = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano']));

    $result = app(DeleteContentLanguageAction::class)->execute($language);

    expect($result)->toBeTrue()
        ->and(ContentLanguage::query()->where('code', 'it')->exists())->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Tek-default invariantı
// ──────────────────────────────────────────────────────────────────────────────

it('enforces a single default on create (new default demotes the previous one)', function (): void {
    $first = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));
    $second = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'is_default' => true]));

    expect(ContentLanguage::query()->where('is_default', true)->pluck('code')->all())->toBe(['tr'])
        ->and(ContentLanguage::query()->find($first->id)->is_default)->toBeFalse()
        ->and($second->is_default)->toBeTrue();
});

it('enforces a single default on update (promoting another demotes the current)', function (): void {
    $en = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));
    $tr = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe']));

    app(UpdateContentLanguageAction::class)->execute(
        $tr,
        contentLanguageDto(['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'is_default' => true]),
    );

    expect(ContentLanguage::query()->where('is_default', true)->pluck('code')->all())->toBe(['tr'])
        ->and(ContentLanguage::query()->find($en->id)->is_default)->toBeFalse();
});

it('coerces an inactive default to active (the default must always be active)', function (): void {
    $language = app(CreateContentLanguageAction::class)->execute(
        contentLanguageDto(['code' => 'en', 'is_default' => true, 'is_active' => false]),
    );

    expect($language->is_active)->toBeTrue()
        ->and($language->is_default)->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Son-default guard'ı
// ──────────────────────────────────────────────────────────────────────────────

it('refuses to demote the only active default (is_default → false)', function (): void {
    $en = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    expect(fn () => app(UpdateContentLanguageAction::class)->execute(
        $en,
        contentLanguageDto(['code' => 'en', 'is_default' => false]),
    ))->toThrow(ApiException::class);

    expect(ContentLanguage::query()->find($en->id)->is_default)->toBeTrue();
});

it('refuses to deactivate the only active default (is_active → false)', function (): void {
    $en = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    try {
        app(UpdateContentLanguageAction::class)->execute(
            $en,
            contentLanguageDto(['code' => 'en', 'is_default' => true, 'is_active' => false]),
        );
        $this->fail('Deactivating the last default should throw.');
    } catch (ApiException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    expect(ContentLanguage::query()->find($en->id)->is_active)->toBeTrue();
});

it('refuses to delete the default content language', function (): void {
    $en = app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    try {
        app(DeleteContentLanguageAction::class)->execute($en);
        $this->fail('Deleting the default language should throw.');
    } catch (ApiException $e) {
        expect($e->getStatusCode())->toBe(422);
    }

    expect(ContentLanguage::query()->where('code', 'en')->exists())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. is_active filtresi (datatable allow-list)
// ──────────────────────────────────────────────────────────────────────────────

it('filters by is_active', function (): void {
    app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_active' => true]));
    app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'tr', 'name' => 'Turkish', 'native_name' => 'Türkçe', 'is_active' => false]));

    $active = ContentLanguage::query()->where('is_active', true)->pluck('code')->all();
    $inactive = ContentLanguage::query()->where('is_active', false)->pluck('code')->all();

    expect($active)->toBe(['en'])
        ->and($inactive)->toBe(['tr']);
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. Validation — unique(code) + exists(fallback_code)
// ──────────────────────────────────────────────────────────────────────────────

it('rejects a duplicate code on create (unique:content_languages,code)', function (): void {
    app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    $rules = (new StoreContentLanguageRequest)->rules();

    $validator = Validator::make([
        'code' => 'en',
        'name' => 'English Duplicate',
        'native_name' => 'English',
        'direction' => 'ltr',
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('code'))->toBeTrue();
});

it('accepts a unique code on create', function (): void {
    app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    $validator = Validator::make([
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'direction' => 'ltr',
    ], (new StoreContentLanguageRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects a fallback_code that does not exist (exists:content_languages,code)', function (): void {
    app(CreateContentLanguageAction::class)->execute(contentLanguageDto(['code' => 'en', 'is_default' => true]));

    $validator = Validator::make([
        'code' => 'tr',
        'name' => 'Turkish',
        'native_name' => 'Türkçe',
        'direction' => 'ltr',
        'fallback_code' => 'zz', // mevcut değil
    ], (new StoreContentLanguageRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('fallback_code'))->toBeTrue();
});

it('rejects an invalid direction (in:ltr,rtl)', function (): void {
    $validator = Validator::make([
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'direction' => 'sideways',
    ], (new StoreContentLanguageRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('direction'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 6. Permission gating — FormRequest authorize()
// ──────────────────────────────────────────────────────────────────────────────

it('denies store for an actor without settings.update', function (): void {
    bindContentLanguageGate();

    $request = StoreContentLanguageRequest::create('/settings/content-languages', 'POST');
    $request->setUserResolver(fn () => contentLanguageActor(['settings.read']));

    expect($request->authorize())->toBeFalse();
});

it('allows store for an actor with settings.update', function (): void {
    bindContentLanguageGate();

    $request = StoreContentLanguageRequest::create('/settings/content-languages', 'POST');
    $request->setUserResolver(fn () => contentLanguageActor(['settings.update']));

    expect($request->authorize())->toBeTrue();
});

it('denies update for an actor without settings.update', function (): void {
    bindContentLanguageGate();

    $request = UpdateContentLanguageRequest::create('/settings/content-languages/1', 'PUT');
    $request->setUserResolver(fn () => contentLanguageActor(['settings.read']));

    expect($request->authorize())->toBeFalse();
});

it('allows update for an actor with settings.update', function (): void {
    bindContentLanguageGate();

    $request = UpdateContentLanguageRequest::create('/settings/content-languages/1', 'PUT');
    $request->setUserResolver(fn () => contentLanguageActor(['settings.update']));

    expect($request->authorize())->toBeTrue();
});

it('treats a guest (no user) as unauthorized for store and update', function (): void {
    bindContentLanguageGate();

    $store = StoreContentLanguageRequest::create('/settings/content-languages', 'POST');
    $store->setUserResolver(fn () => null);

    $update = UpdateContentLanguageRequest::create('/settings/content-languages/1', 'PUT');
    $update->setUserResolver(fn () => null);

    expect($store->authorize())->toBeFalse()
        ->and($update->authorize())->toBeFalse();
});
