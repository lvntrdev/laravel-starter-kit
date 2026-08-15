<?php

/*
|--------------------------------------------------------------------------
| Two-factor challenge is single-use (red-line regression)
|--------------------------------------------------------------------------
|
| The API 2FA challenge used to be read with Cache::get() and only forgotten on
| a SUCCESSFUL verification, so every failed attempt left the key alive for the
| rest of its 5-minute TTL — a 6-digit code brute-forced behind one issued
| challenge. It was then claimed with Cache::pull(), which reads like a claim
| but is a get() followed by a forget() in Illuminate\Cache\Repository — two
| requests could both read the user id before either delete landed, and both
| could mint a token.
|
| TwoFactorChallengeAction now claims the challenge with Cache::add() on a
| companion key as its FIRST statement: add-if-absent, atomic inside the store,
| and it reports which caller created the key. Exactly one redemption gets past
| that line; the payload pull follows behind it.
|
| WHAT THIS FILE PROVES, AND WHAT IT CANNOT
|
| It proves the gate: exactly ONE of two redemptions of the same challenge
| reaches verification, a redemption is refused while another holds the claim
| even though the payload is still readable, a failed attempt cannot be retried,
| and a second redemption yields null.
|
| It cannot SCHEDULE a genuine race — the package suite runs the array driver in
| a single process. What it does instead is assert the invariant the race would
| violate (one caller past the gate) and pin the primitive structurally, since
| the array store is the ONE store with no add() of its own: correctness there
| rests on it being per-process memory, and on every real driver (redis,
| memcached, database, file) add() is a single store-level operation.
|
| Nor does it reach the SUCCESS branch: that ends in Passport's createToken(),
| which needs oauth tables, a client and encryption keys. "Exactly one success"
| is therefore measured one step earlier, at the verification call — the point
| past which a second caller must never arrive.
|
| The recovery-code row lock is pinned structurally at the bottom for a third
| reason — on SQLite lockForUpdate compiles to a no-op, so a double-spend there
| is unreproducible too.
|
| Every secret-shaped value below is a synthetic placeholder.
|
*/

use App\Domain\Auth\Actions\LoginUserAction;
use App\Domain\Auth\Actions\TwoFactorChallengeAction;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

$stubs = dirname(__DIR__, 3).'/stubs';

if (! class_exists(User::class)) {
    require_once $stubs.'/app/Models/User.php';
}

if (! class_exists(LoginUserAction::class)) {
    require_once $stubs.'/app/Domain/Auth/Actions/LoginUserAction.php';
}

if (! class_exists(TwoFactorChallengeAction::class)) {
    require_once $stubs.'/app/Domain/Auth/Actions/TwoFactorChallengeAction.php';
}

/** A synthetic TOTP shared secret — not a credential. */
const TWO_FACTOR_PLACEHOLDER_SECRET = 'SYNTHETICPLACEHOLDERSECRET';

beforeEach(function (): void {
    // DatabaseTestCase's `users` shim is deliberately minimal; the consumer
    // User model needs a few more columns before User::find() can run at all
    // (SoftDeletes alone would fail on the missing deleted_at).
    Schema::table('users', function (Blueprint $table): void {
        foreach ([
            'status' => fn () => $table->string('status')->nullable(),
            'two_factor_secret' => fn () => $table->text('two_factor_secret')->nullable(),
            'two_factor_recovery_codes' => fn () => $table->text('two_factor_recovery_codes')->nullable(),
            'two_factor_confirmed_at' => fn () => $table->timestamp('two_factor_confirmed_at')->nullable(),
            'deleted_at' => fn () => $table->softDeletes(),
        ] as $column => $add) {
            if (! Schema::hasColumn('users', $column)) {
                $add();
            }
        }
    });

    // FortifyServiceProvider is not booted in the package suite, so the
    // verification provider the action type-hints has no binding. Every test
    // gets the recording stub; the ones that care re-bind it against their own
    // cache key.
    bindRecordingTwoFactorProvider('unused-challenge-key');
});

/**
 * Seed a 2FA-enrolled, active user straight through the query builder.
 *
 * Not through the model: HasUuids would mint a uuid key the integer-keyed
 * `users` shim cannot hold, and none of that is what is under test here.
 */
function seedTwoFactorUser(): int
{
    return (int) DB::table('users')->insertGetId([
        'name' => 'Ada',
        'email' => '2fa-'.uniqid().'@example.test',
        'password' => 'SYNTHETIC-PLACEHOLDER-HASH',
        'status' => 'active',
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt(TWO_FACTOR_PLACEHOLDER_SECRET),
        'two_factor_confirmed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Put a challenge in the cache exactly the way LoginUserAction does.
 *
 * @return array{0: string, 1: string} [challenge, cache key]
 */
function issueTwoFactorChallenge(int|string $userId): array
{
    $challenge = 'synthetic-challenge-'.uniqid();
    $key = LoginUserAction::challengeKey($challenge);

    Cache::put($key, $userId, 300);

    return [$challenge, $key];
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. The challenge is claimed before anything else can fail
// ──────────────────────────────────────────────────────────────────────────────

it('consumes the challenge even when the attempt goes nowhere', function (): void {
    // Points at a user that does not exist: the action bails on the very first
    // branch AFTER the claim. Under the old Cache::get()-then-forget-on-success
    // shape the key would still be sitting there.
    [$challenge, $key] = issueTwoFactorChallenge(999999);

    $result = app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null);

    expect($result)->toBeNull()
        ->and(Cache::has($key))->toBeFalse();
});

it('returns null on a second redemption of the same challenge', function (): void {
    [$challenge, $key] = issueTwoFactorChallenge(999999);

    app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null);

    expect(Cache::has($key))->toBeFalse()
        ->and(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull();
});

it('returns null for a challenge that was never issued', function (): void {
    expect(app(TwoFactorChallengeAction::class)->execute('never-issued-'.uniqid(), '123456', null))->toBeNull();
});

it('consumes the challenge when no code and no recovery code are supplied', function (): void {
    [$challenge, $key] = issueTwoFactorChallenge(999999);

    expect(app(TwoFactorChallengeAction::class)->execute($challenge, null, null))->toBeNull()
        ->and(Cache::has($key))->toBeFalse();
});

it('consumes the challenge when the user is not 2FA-enrolled', function (): void {
    $id = (int) DB::table('users')->insertGetId([
        'name' => 'Grace',
        'email' => 'no-2fa-'.uniqid().'@example.test',
        'password' => 'SYNTHETIC-PLACEHOLDER-HASH',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    [$challenge, $key] = issueTwoFactorChallenge($id);

    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull()
        ->and(Cache::has($key))->toBeFalse();
});

it('consumes the challenge for a user whose account is no longer active', function (): void {
    $id = seedTwoFactorUser();
    DB::table('users')->where('id', $id)->update(['status' => 'suspended']);

    [$challenge, $key] = issueTwoFactorChallenge($id);

    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull()
        ->and(Cache::has($key))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. The key is already gone at the moment verification runs
// ──────────────────────────────────────────────────────────────────────────────

/**
 * A verification provider that records the cache state at the exact moment it
 * is asked to verify, then refuses the code.
 */
function bindRecordingTwoFactorProvider(string $cacheKey): object
{
    $spy = new class
    {
        public bool $called = false;

        /** How many redemptions made it all the way to verification. */
        public int $calls = 0;

        public bool $challengeStillCached = true;

        public string $key = '';
    };

    $spy->key = $cacheKey;

    app()->instance(TwoFactorAuthenticationProvider::class, new class($spy) implements TwoFactorAuthenticationProvider
    {
        public function __construct(private object $spy) {}

        public function generateSecretKey($length = 16, $prefix = ''): string
        {
            return TWO_FACTOR_PLACEHOLDER_SECRET;
        }

        public function qrCodeUrl($companyName, $companyEmail, $secret): string
        {
            return '';
        }

        public function verify(
            #[SensitiveParameter] $secret,
            #[SensitiveParameter] $code,
        ): bool {
            $this->spy->called = true;
            $this->spy->calls++;
            $this->spy->challengeStillCached = Cache::has($this->spy->key);

            return false;
        }
    });

    return $spy;
}

it('has already deleted the challenge key before the TOTP code is verified', function (): void {
    $id = seedTwoFactorUser();
    [$challenge, $key] = issueTwoFactorChallenge($id);

    $spy = bindRecordingTwoFactorProvider($key);

    $result = app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null);

    expect($spy->called)->toBeTrue()
        // This is what Cache::pull() as the first statement buys: verification
        // runs against a challenge that has already been spent.
        ->and($spy->challengeStillCached)->toBeFalse()
        ->and($result)->toBeNull()
        ->and(Cache::has($key))->toBeFalse();
});

it('cannot replay a challenge after a wrong TOTP code', function (): void {
    $id = seedTwoFactorUser();
    [$challenge, $key] = issueTwoFactorChallenge($id);

    $spy = bindRecordingTwoFactorProvider($key);

    // First attempt: wrong code.
    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '000000', null))->toBeNull();

    $spy->called = false;

    // Second attempt with the same challenge — the code no longer matters, the
    // action must bail before it ever reaches the provider.
    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull()
        ->and($spy->called)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Exactly one redemption gets past the claim
// ──────────────────────────────────────────────────────────────────────────────

it('lets exactly one of two redemptions of the same challenge reach verification', function (): void {
    $id = seedTwoFactorUser();
    [$challenge, $key] = issueTwoFactorChallenge($id);

    $spy = bindRecordingTwoFactorProvider($key);

    // Two redemptions of ONE challenge. Sequential here — the array store runs
    // in a single process — but the gate they contend for is the same one a
    // concurrent pair would hit, and only one caller may cross it.
    $first = app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null);
    $second = app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null);

    expect($spy->calls)->toBe(1)
        ->and($first)->toBeNull()
        ->and($second)->toBeNull();
});

it('refuses a redemption while another holds the claim, even with the payload still cached', function (): void {
    // The interleaving the old Cache::pull() shape could not survive: a first
    // caller has claimed the challenge but has not deleted the payload yet.
    // Under read-then-delete the second caller reads the very same user id and
    // proceeds. Under the atomic claim it must be refused, and it must leave
    // the payload alone — that payload still belongs to the winner.
    $id = seedTwoFactorUser();
    [$challenge, $key] = issueTwoFactorChallenge($id);

    $spy = bindRecordingTwoFactorProvider($key);

    Cache::add(LoginUserAction::challengeClaimKey($challenge), true, 300);

    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull()
        ->and($spy->called)->toBeFalse()
        ->and(Cache::has($key))->toBeTrue();
});

it('keeps the claim standing after a failed attempt, so the challenge cannot come back', function (): void {
    // The claim outliving the payload is what makes a failed attempt final: the
    // payload is gone AND the key that would let a retry re-enter is taken.
    $id = seedTwoFactorUser();
    [$challenge, $key] = issueTwoFactorChallenge($id);

    bindRecordingTwoFactorProvider($key);

    app(TwoFactorChallengeAction::class)->execute($challenge, '000000', null);

    expect(Cache::has(LoginUserAction::challengeClaimKey($challenge)))->toBeTrue()
        ->and(Cache::has($key))->toBeFalse();

    // Even re-issuing the payload under the same challenge id cannot revive it.
    Cache::put($key, $id, 300);

    expect(app(TwoFactorChallengeAction::class)->execute($challenge, '123456', null))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Guards for the two properties this driver/DB cannot demonstrate
// ──────────────────────────────────────────────────────────────────────────────

it('claims the challenge with an atomic add-if-absent, ahead of every read and every branch', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/stubs/app/Domain/Auth/Actions/TwoFactorChallengeAction.php');

    $body = substr($source, (int) strpos($source, 'public function execute('));

    expect($body)->toContain('Cache::add(')
        ->toContain('challengeClaimKey($challenge)')
        // A get()/forget() pair is the shape this started as; a bare pull() as
        // the claim is the shape that replaced it and was still racy.
        ->not->toContain('Cache::get(')
        ->not->toContain('Cache::forget(');

    // The atomic claim must come first — before the payload is read, and before
    // either verification branch. A claim placed after the read claims nothing.
    $claim = (int) strpos($body, 'Cache::add(');

    expect($claim)->toBeLessThan((int) strpos($body, 'Cache::pull('));
    expect($claim)->toBeLessThan((int) strpos($body, '$this->provider->verify('));
    expect($claim)->toBeLessThan((int) strpos($body, '$this->consumeRecoveryCode('));
});

it('gives the claim the same TTL as the challenge it guards', function (): void {
    // Written later than the payload with an equal TTL, the claim always
    // expires later — so there is no window where the payload is alive and the
    // door is unlocked. A shorter, hand-written TTL would open exactly that.
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/stubs/app/Domain/Auth/Actions/TwoFactorChallengeAction.php');

    expect($source)->toContain('LoginUserAction::TWO_FACTOR_CHALLENGE_TTL');
});

it('spends a recovery code under a row lock, re-read inside the transaction', function (): void {
    // On SQLite lockForUpdate compiles to a no-op (the whole transaction is
    // serialized instead), so the concurrent double-spend this prevents cannot
    // be reproduced here — the structure is pinned instead.
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/stubs/app/Domain/Auth/Actions/TwoFactorChallengeAction.php');

    $body = substr($source, (int) strpos($source, 'private function consumeRecoveryCode('));

    expect($body)->toContain('DB::transaction(')
        ->toContain('lockForUpdate()');

    // The re-read inside the lock is the point: matching against the caller's
    // already-loaded $user would defeat the serialization entirely.
    expect(strpos($body, 'lockForUpdate()'))->toBeLessThan((int) strpos($body, 'recoveryCodes()'));
    expect(strpos($body, 'recoveryCodes()'))->toBeLessThan((int) strpos($body, 'replaceRecoveryCode('));
});
