<?php

use App\Http\Middleware\EnsurePasswordNotExpired;
use Carbon\CarbonInterface;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Password Expiry — EnsurePasswordNotExpired (security-settings-redesign T3)
|--------------------------------------------------------------------------
|
| Middleware sözleşmesi:
|   - expiry_days <= 0 veya config yok → bypass (mevcut kurulum güvenliği).
|   - password_changed_at null → muaf (backfill öncesi satır kilitlenmez).
|   - Süresi dolan → adanmış password.expired ekranına redirect.
|   - Muaf rotalar (password.expired, logout, Fortify password/2FA uçları) süresi
|     dolmuş kullanıcı için DAİMA erişilebilir → redirect loop imkânsız.
|
| Rotalar test içinde middleware ile sarılarak kurulur; gerçek kayıt noktası
| (stubs/routes/web.php auth grubu) içerik guard'ı ile doğrulanır.
|
*/

require_once dirname(__DIR__, 3).'/stubs/app/Http/Middleware/EnsurePasswordNotExpired.php';

/**
 * Inline users tablosu üzerinde minimal Authenticatable aktör
 * (TrashedMediaAccessTest deseni).
 */
function passwordExpiryActor(CarbonInterface|string|null $changedAt): Authenticatable
{
    $actor = new class extends Model implements Authenticatable
    {
        use AuthenticatableTrait;

        protected $table = 'users';

        protected $guarded = [];

        protected function casts(): array
        {
            return ['password_changed_at' => 'datetime'];
        }
    };

    return $actor::create([
        'name' => 'Expiry Probe',
        'email' => uniqid('expiry-', true).'@example.test',
        'password' => bcrypt('irrelevant'),
        'password_changed_at' => $changedAt,
    ]);
}

function passwordExpiryDays(?int $days): void
{
    config(['starter-kit.password_policy.expiry_days' => $days]);
}

beforeEach(function (): void {
    Route::middleware(['web', EnsurePasswordNotExpired::class])->group(function (): void {
        Route::get('/__expiry/guarded', fn () => response('guarded-ok'))->name('expiry.guarded');

        // Muaf rotaların test ikizleri — middleware ada göre muaf tutar.
        Route::get('/password-expired', fn () => response('password-expired-ok'))->name('password.expired');
        Route::post('/logout', fn () => response('logout-ok'))->name('logout');
        Route::put('/user/password', fn () => response('password-update-ok'))->name('user-password.update');
        Route::get('/two-factor-challenge', fn () => response('two-factor-ok'))->name('two-factor.login');
    });
});

it('bypasses when expiry is 0 (unlimited) even with an ancient stamp', function (): void {
    passwordExpiryDays(0);

    $this->actingAs(passwordExpiryActor(now()->subYears(5)))
        ->get('/__expiry/guarded')
        ->assertOk();
});

it('bypasses when the policy config is absent (existing install before bridge)', function (): void {
    config(['starter-kit.password_policy' => null]);

    $this->actingAs(passwordExpiryActor(now()->subYears(5)))
        ->get('/__expiry/guarded')
        ->assertOk();
});

it('lets a fresh password through', function (): void {
    passwordExpiryDays(90);

    $this->actingAs(passwordExpiryActor(now()->subDays(10)))
        ->get('/__expiry/guarded')
        ->assertOk();
});

it('redirects an expired password to the dedicated password-expired screen', function (): void {
    passwordExpiryDays(90);

    $this->actingAs(passwordExpiryActor(now()->subDays(120)))
        ->get('/__expiry/guarded')
        ->assertRedirect(route('password.expired'));
});

it('treats the exact expiry boundary as expired', function (): void {
    passwordExpiryDays(90);

    $this->actingAs(passwordExpiryActor(now()->subDays(90)))
        ->get('/__expiry/guarded')
        ->assertRedirect(route('password.expired'));
});

it('exempts a null stamp instead of hard-locking the account', function (): void {
    passwordExpiryDays(90);

    $this->actingAs(passwordExpiryActor(null))
        ->get('/__expiry/guarded')
        ->assertOk();
});

it('ignores guests — unauthenticated requests pass through untouched', function (): void {
    passwordExpiryDays(90);

    $this->get('/__expiry/guarded')->assertOk();
});

it('never loops: an expired user can always reach the exempt escape-hatch routes', function (): void {
    passwordExpiryDays(90);

    $expired = passwordExpiryActor(now()->subDays(365));

    // Şifre formunu barındıran adanmış sayfa (redirect hedefi) erişilebilir → loop yok.
    $this->actingAs($expired)->get('/password-expired')->assertOk();

    // Şifreyi düzeltecek Fortify ucu erişilebilir.
    $this->actingAs($expired)->put('/user/password')->assertOk();

    // Çıkış her zaman mümkün.
    $this->actingAs($expired)->post('/logout')->assertOk();

    // 2FA challenge muaf (defensive — normalde grup dışında).
    $this->actingAs($expired)->get('/two-factor-challenge')->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// İçerik guard'ları — gerçek kayıt noktası + lang anahtarı
// ──────────────────────────────────────────────────────────────────────────────

it('is registered on the authenticated panel route group in the stub web routes', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/routes/web.php');

    expect($contents)
        ->toContain('use App\Http\Middleware\EnsurePasswordNotExpired;')
        ->toContain("Route::middleware(['auth', 'verified', EnsurePasswordNotExpired::class])");
});

it('ships the password_expired flash message in both kit languages', function (): void {
    $en = require dirname(__DIR__, 3).'/resources/lang/en/sk-auth.php';
    $tr = require dirname(__DIR__, 3).'/resources/lang/tr/sk-auth.php';

    expect($en)->toHaveKey('password_expired')
        ->and($tr)->toHaveKey('password_expired');
});
