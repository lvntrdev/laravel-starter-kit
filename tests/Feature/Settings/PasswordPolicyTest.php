<?php

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
|--------------------------------------------------------------------------
| Password Policy — dinamik kurallar + stamping (security-settings-redesign T3)
|--------------------------------------------------------------------------
|
| 1. PasswordPolicy::rule() — köprülenen starter-kit.password_policy.*
|    config'inden kural üretir; config YOKKEN (settings tablosu olmayan
|    artisan bağlamı) sertleştirilmiş statik baseline'a (min 10 + mixed
|    case + numbers + symbols — policy öncesi davranış) fail-closed düşer.
| 2. PasswordValidationRules trait'i (Fortify register/reset/update) aynı
|    dinamik kuralı kullanır — tek politika kaynağı.
| 3. Stamping — stub User modelindeki saving hook'u şifre yazan HER
|    Eloquent yolunda password_changed_at'i damgalar; açık atama kazanır.
| 4. İçerik guard'ları — AppServiceProvider'ın Password::defaults()'u
|    PasswordPolicy'ye bağladığını (admin + API request kapsamı) doğrular.
|
| Stub sınıfları App\ namespace'inde autoload edilmez — dosyalar doğrudan
| yüklenir (AuthSettingsTest ile aynı desen).
|
*/

require_once dirname(__DIR__, 3).'/stubs/app/Support/PasswordPolicy.php';
require_once dirname(__DIR__, 3).'/stubs/app/Actions/Fortify/PasswordValidationRules.php';

if (! class_exists(User::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/User.php';
}

/**
 * Köprü koşmuş gibi runtime config'i kurar. Taban bilinçli olarak NÖTR /
 * permissive (min 8, kurallar kapalı) — her test yalnız kendi kuralını
 * izole eder. Köprünün GERÇEK fallback'leri bunlar DEĞİL: pre-feature
 * hardened baseline (min 10 + tüm kurallar) — aşağıdaki baseline testi ve
 * AuthSettingsTest'teki köprü testleri o değerleri ayrıca doğrular.
 */
function passwordPolicyBridge(array $overrides = []): void
{
    config(['starter-kit.password_policy' => $overrides + [
        'min_length' => 8,
        'expiry_days' => 0,
        'require_mixed_case' => false,
        'require_numbers' => false,
        'require_symbols' => false,
    ]]);
}

function passwordRuleAccepts(string $password): bool
{
    return Validator::make(
        ['password' => $password],
        ['password' => ['required', 'string', PasswordPolicy::rule()]],
    )->passes();
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. PasswordPolicy::rule() — config'ten dinamik kurallar
// ──────────────────────────────────────────────────────────────────────────────

it('enforces the configured minimum length', function (): void {
    passwordPolicyBridge(['min_length' => 12]);

    expect(passwordRuleAccepts(str_repeat('a', 11)))->toBeFalse()
        ->and(passwordRuleAccepts(str_repeat('a', 12)))->toBeTrue();
});

it('bridged fallbacks equal the pre-feature hardened baseline', function (): void {
    // Mevcut kurulum, DB'de yeni anahtar yok → köprü pre-feature hardened
    // baseline'ı yazar: eski stub AppServiceProvider Password::defaults()'u
    // min 10 + mixed case + numbers + symbols'a yükseltiyordu. Fallback bu
    // baseline'a EŞİTTİR — daha zayıf bir değer sk:update sonrası mevcut
    // kurulumun parola politikasını sessizce gevşetirdi (final review B1).
    passwordPolicyBridge([
        'min_length' => 10,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
    ]);

    expect(passwordRuleAccepts('Aa1!aaaaaa'))->toBeTrue()       // 10 + tüm sınıflar
        ->and(passwordRuleAccepts('Aa1!aaaaa'))->toBeFalse()    // 9 < 10
        ->and(passwordRuleAccepts('aaaaaaaaaa'))->toBeFalse()   // sınıf kuralları eksik
        ->and(passwordRuleAccepts('Aa1aaaaaaa'))->toBeFalse();  // sembol yok
});

it('applies each rule toggle independently', function (string $toggle, string $failing, string $passing): void {
    passwordPolicyBridge([$toggle => true]);

    expect(passwordRuleAccepts($failing))->toBeFalse()
        ->and(passwordRuleAccepts($passing))->toBeTrue();
})->with([
    'mixed case' => ['require_mixed_case', 'alllower1!aa', 'Mixedcase1aa'],
    'numbers' => ['require_numbers', 'nodigitshere', 'hasdigit1aaa'],
    'symbols' => ['require_symbols', 'nosymbol1Aaa', 'symbol!aaaaa'],
]);

it('enforces all toggles together with the configured minimum', function (): void {
    passwordPolicyBridge([
        'min_length' => 10,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
    ]);

    expect(passwordRuleAccepts('Str0ng!Pass'))->toBeTrue()
        ->and(passwordRuleAccepts('Sh0rt!Pw'))->toBeFalse()      // < 10
        ->and(passwordRuleAccepts('str0ng!pass'))->toBeFalse()   // mixed case yok
        ->and(passwordRuleAccepts('Strong!Pass'))->toBeFalse()   // rakam yok
        ->and(passwordRuleAccepts('Str0ngPass1'))->toBeFalse();  // sembol yok
});

it('falls back to the hardened static baseline when the bridge has not run', function (): void {
    // Settings tablosu yokken köprü erken döner → config absent. Fail-closed:
    // policy öncesi sertleştirilmiş baseline (min 10 + tüm kurallar) geçerli.
    config(['starter-kit.password_policy' => null]);

    expect(config('starter-kit.password_policy.min_length'))->toBeNull()
        ->and(passwordRuleAccepts('Aa1!aaaaa'))->toBeFalse()      // 9 < 10
        ->and(passwordRuleAccepts('Aa1!aaaaaa'))->toBeTrue()      // baseline'ı karşılar
        ->and(passwordRuleAccepts('aaaaaaaaaaaa'))->toBeFalse()   // kurallar eksik
        ->and(passwordRuleAccepts('Aa1aaaaaaa'))->toBeFalse();    // sembol yok
});

it('clamps a corrupted min_length so Password::min(0) can never be built', function (): void {
    passwordPolicyBridge(['min_length' => 0]);

    expect(passwordRuleAccepts(''))->toBeFalse()
        ->and(passwordRuleAccepts('a'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. PasswordValidationRules trait — Fortify akışları aynı politikayı kullanır
// ──────────────────────────────────────────────────────────────────────────────

function fortifyPasswordRules(): array
{
    $harness = new class
    {
        use PasswordValidationRules;

        /** @return array<int, mixed> */
        public function exposed(): array
        {
            return $this->passwordRules();
        }
    };

    return $harness->exposed();
}

it('trait keeps the required/string/confirmed contract around the dynamic rule', function (): void {
    passwordPolicyBridge();

    $rules = fortifyPasswordRules();

    expect($rules)->toContain('required')
        ->toContain('string')
        ->toContain('confirmed')
        ->and(collect($rules)->first(fn ($rule) => $rule instanceof Password))
        ->not->toBeNull();
});

it('trait rules follow the configured policy at validation time', function (): void {
    passwordPolicyBridge(['min_length' => 12, 'require_numbers' => true]);

    $validate = fn (string $password): bool => Validator::make(
        ['password' => $password, 'password_confirmation' => $password],
        ['password' => fortifyPasswordRules()],
    )->passes();

    expect($validate('onlyletters!'))->toBeFalse()        // rakam yok
        ->and($validate('short1!'))->toBeFalse()           // < 12
        ->and($validate('longenough1!'))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Stamping — stub User saving hook'u (model sınırında, atlanamaz)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Gerçek stub User modelinin saving hook'unu miras alan test modeli.
 * Inline users tablosu (int PK) HasUuids ile uyumsuz olduğundan testler
 * consumer şemasını yansıtan ayrı bir tabloda koşar.
 */
function stampingUserClass(): string
{
    static $class = null;

    if ($class === null) {
        $class = (new class extends User
        {
            protected $table = 'policy_test_users';
        })::class;
    }

    return $class;
}

beforeEach(function (): void {
    // Spatie activitylog provider'ı paket test ortamında kayıtlı değil —
    // User'daki HasActivityLogging hook'ları yazım yapmasın.
    config(['activitylog.enabled' => false]);

    if (! Schema::hasTable('policy_test_users')) {
        Schema::create('policy_test_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('password');
            $table->timestamp('password_changed_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
});

it('stamps password_changed_at on create at the model boundary', function (): void {
    $user = stampingUserClass()::create([
        'first_name' => 'Stamp',
        'last_name' => 'Test',
        'email' => 'stamp-create@example.test',
        'password' => 'Initial-Secret-1!',
    ]);

    expect($user->password_changed_at)->not->toBeNull()
        ->and($user->password_changed_at->diffInSeconds(now()))->toBeLessThan(5);
});

it('re-stamps on every password write but not on unrelated updates', function (): void {
    $user = stampingUserClass()::create([
        'first_name' => 'Stamp',
        'last_name' => 'Rotate',
        'email' => 'stamp-rotate@example.test',
        'password' => 'Initial-Secret-1!',
    ]);

    // Damgayı geçmişe çek (şifre dirty değil → hook dokunmaz, açık atama kazanır).
    $past = now()->subDays(120)->startOfSecond();
    $user->forceFill(['password_changed_at' => $past])->save();
    expect($user->refresh()->password_changed_at->equalTo($past))->toBeTrue();

    // Şifre dışı güncelleme damgayı DEĞİŞTİRMEZ.
    $user->forceFill(['first_name' => 'Renamed'])->save();
    expect($user->refresh()->password_changed_at->equalTo($past))->toBeTrue();

    // Şifre yazımı (Fortify reset/update ile aynı forceFill yolu) damgayı yeniler.
    $user->forceFill(['password' => 'Rotated-Secret-2!'])->save();
    expect($user->refresh()->password_changed_at->greaterThan($past))->toBeTrue();
});

it('lets an explicit password_changed_at win over the automatic stamp', function (): void {
    $explicit = now()->subDays(30)->startOfSecond();

    $user = stampingUserClass()::create([
        'first_name' => 'Stamp',
        'last_name' => 'Explicit',
        'email' => 'stamp-explicit@example.test',
        'password' => 'Initial-Secret-1!',
    ]);

    $user->forceFill([
        'password' => 'Seeded-Secret-3!',
        'password_changed_at' => $explicit,
    ])->save();

    expect($user->refresh()->password_changed_at->equalTo($explicit))->toBeTrue();
});

it('does not expose password_changed_at to mass assignment', function (): void {
    expect(in_array('password_changed_at', (new User)->getFillable(), true))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. İçerik guard'ları — kayıt noktaları
// ──────────────────────────────────────────────────────────────────────────────

it('AppServiceProvider binds Password::defaults() to the dynamic PasswordPolicy', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Providers/AppServiceProvider.php');

    // Admin + API user CRUD request'leri Password::defaults() kullanır —
    // bu binding olmadan onlar statik kalır, tek-politika sözleşmesi kırılır.
    expect($contents)
        ->toContain('use App\Support\PasswordPolicy;')
        ->toContain('Password::defaults(fn (): Password => PasswordPolicy::rule())');
});

it('the Fortify trait resolves its rule through PasswordPolicy', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Actions/Fortify/PasswordValidationRules.php');

    expect($contents)->toContain('PasswordPolicy::rule()');
});

it('the stub User model carries the model-boundary stamping hook', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Models/User.php');

    expect($contents)
        ->toContain('static::saving(')
        ->toContain("isDirty('password')")
        ->toContain('password_changed_at');
});
