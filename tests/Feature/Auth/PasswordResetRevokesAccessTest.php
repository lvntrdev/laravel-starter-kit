<?php

/*
|--------------------------------------------------------------------------
| Password reset → credential revocation
|--------------------------------------------------------------------------
|
| A password reset is universally read as "whatever the old password bought is
| gone". Nothing in Laravel, Fortify or Passport makes that true: a bearer
| token is bound to a client and a user, never to a password, and a database
| session is bound to a cookie. So before this listener existed, the user who
| reset their password BECAUSE they believed they were compromised changed
| nothing for the attacker — the stolen token kept authenticating for the rest
| of its lifetime (the kit's own defaults: 14 days for a refresh token, 30 for
| a personal access token) and the attacker's browser session kept working
| until the session lifetime ran out.
|
| What is locked here:
|
|   1. The reset drops the WHOLE credential surface: access token, the refresh
|      token bound to it, the unredeemed authorization and device codes that
|      could still be exchanged for a new token, and the account's database
|      session rows.
|   2. It is STATUS-INDEPENDENT. The account is active before the reset and
|      active after it, and `enforce_active_status` is off — the two conditions
|      under which RevokeUserAccessAction::execute() deliberately does nothing.
|      Routing the reset through that method instead of
|      executeForCredentialRotation() is the regression this pins.
|   3. The blast radius is one account. Another user's credentials survive, and
|      so does the GUEST session row — the one the reset request itself is
|      holding, since Fortify resets without logging anyone in. That is what
|      makes purging sessions mid-reset safe for the redirect to login.
|   4. The risk notes hold: a non-`database` session driver silently keeps its
|      sessions (there is no user→session index to delete by) and still revokes
|      the tokens, and an event carrying something that is not Authenticatable
|      never turns a successful reset into a 500.
|
| Wiring is exercised, not mocked: the test fires the framework event through
| the real dispatcher, so removing the Event::listen line in
| StarterKitServiceProvider fails this file.
|
| Helpers carry a `prr` prefix — Pest helpers are global for the whole process.
|
*/

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\User\Actions\RevokeUserAccessAction;
use Lvntr\StarterKit\Domain\User\Listeners\RevokeCredentialsOnPasswordReset;

if (! class_exists(User::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/User.php';
}

/**
 * The shipped consumer User model, pointed at a table this file owns.
 *
 * Same reason as tests/Feature/User/ActiveStatusEnforcementTest.php: the
 * shipped model is UUID-keyed with a `status` column, and the revocation path
 * needs HasApiTokens' provider-scoped `tokens()` relation to resolve against a
 * real table on a real connection.
 */
class PasswordResetUser extends User
{
    protected $table = 'password_reset_users';
}

const PRR_CLIENT_ID = '9d0f7777-8888-4999-8aaa-bbbbccccdddd';

function prrUser(string $email = 'ada-reset@example.test', array $attributes = []): PasswordResetUser
{
    return PasswordResetUser::create(array_merge([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => $email,
        'password' => 'Valid-Password-1!',
        'status' => 'active',
    ], $attributes));
}

/**
 * Seed the full credential surface of one account: an access token, the
 * refresh token bound to it, an unredeemed authorization code, a device code
 * and a database session row.
 *
 * @return string the access token id
 */
function prrCredentials(PasswordResetUser $user): string
{
    $tokenId = Str::random(80);

    DB::table('oauth_access_tokens')->insert([
        'id' => $tokenId,
        'user_id' => $user->getKey(),
        'client_id' => PRR_CLIENT_ID,
        'name' => 'stolen token',
        'scopes' => '[]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    DB::table('oauth_refresh_tokens')->insert([
        'id' => Str::random(80),
        'access_token_id' => $tokenId,
        'revoked' => false,
        'expires_at' => now()->addDays(14),
    ]);

    DB::table('oauth_auth_codes')->insert([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => PRR_CLIENT_ID,
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ]);

    DB::table('oauth_device_codes')->insert([
        'id' => Str::random(80),
        'user_id' => $user->getKey(),
        'client_id' => PRR_CLIENT_ID,
        'user_code' => Str::upper(Str::random(8)),
        'scopes' => '[]',
        'revoked' => false,
        'user_approved_at' => now(),
        'last_polled_at' => now(),
        'expires_at' => now()->addMinutes(10),
    ]);

    prrSession($user->getKey());

    return $tokenId;
}

/** Write one session row. A null owner is the guest session of a reset request. */
function prrSession(?string $userId): string
{
    $id = Str::random(40);

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $userId,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'pest',
        'payload' => base64_encode(serialize([])),
        'last_activity' => time(),
    ]);

    return $id;
}

/** Whether every credential class seeded for this account is still usable. */
function prrCredentialsLive(PasswordResetUser $user, string $tokenId): bool
{
    return DB::table('oauth_access_tokens')->where('id', $tokenId)->where('revoked', false)->exists()
        && DB::table('oauth_refresh_tokens')->where('access_token_id', $tokenId)->where('revoked', false)->exists()
        && DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->where('revoked', false)->exists()
        && DB::table('oauth_device_codes')->where('user_id', $user->getKey())->where('revoked', false)->exists()
        && DB::table('sessions')->where('user_id', $user->getKey())->exists();
}

/** Whether every credential class seeded for this account is gone. */
function prrCredentialsRevoked(PasswordResetUser $user, string $tokenId): bool
{
    return ! DB::table('oauth_access_tokens')->where('id', $tokenId)->where('revoked', false)->exists()
        && ! DB::table('oauth_refresh_tokens')->where('access_token_id', $tokenId)->where('revoked', false)->exists()
        && ! DB::table('oauth_auth_codes')->where('user_id', $user->getKey())->where('revoked', false)->exists()
        && ! DB::table('oauth_device_codes')->where('user_id', $user->getKey())->where('revoked', false)->exists()
        && ! DB::table('sessions')->where('user_id', $user->getKey())->exists();
}

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    config(['auth.providers.users.model' => PasswordResetUser::class]);
    config(['session.driver' => 'database']);
    config(['session.connection' => null]);
    config(['session.table' => 'sessions']);

    // Mirrors stubs/database/migrations/0001_01_01_000000_create_users_table.php.
    Schema::create('password_reset_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->text('two_factor_secret')->nullable();
        $table->text('two_factor_recovery_codes')->nullable();
        $table->timestamp('two_factor_confirmed_at')->nullable();
        $table->rememberToken();
        $table->string('status')->nullable()->default('active');
        $table->string('timezone', 64)->nullable();
        $table->softDeletes();
        $table->timestamps();
    });

    // Same shape as the published sessions table: a UUID owner, nullable so a
    // guest session can exist.
    Schema::create('sessions', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->uuid('user_id')->nullable()->index();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->longText('payload');
        $table->integer('last_activity')->index();
    });

    // Passport tables, copied from the migrations the kit publishes
    // (stubs/database/migrations/2026_03_04_2051*).
    Schema::create('oauth_clients', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableUuidMorphs('owner');
        $table->string('name');
        $table->string('secret')->nullable();
        $table->string('provider')->nullable();
        $table->text('redirect_uris');
        $table->text('grant_types');
        $table->boolean('revoked');
        $table->timestamps();
    });

    Schema::create('oauth_access_tokens', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->uuid('user_id')->nullable()->index();
        $table->uuid('client_id');
        $table->string('name')->nullable();
        $table->text('scopes')->nullable();
        $table->boolean('revoked');
        $table->timestamps();
        $table->dateTime('expires_at')->nullable();
    });

    Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->char('access_token_id', 80)->index();
        $table->boolean('revoked');
        $table->dateTime('expires_at')->nullable();
    });

    Schema::create('oauth_auth_codes', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->uuid('user_id')->index();
        $table->uuid('client_id');
        $table->text('scopes')->nullable();
        $table->boolean('revoked');
        $table->dateTime('expires_at')->nullable();
    });

    Schema::create('oauth_device_codes', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->uuid('user_id')->nullable()->index();
        $table->uuid('client_id')->index();
        $table->char('user_code', 8)->unique();
        $table->text('scopes');
        $table->boolean('revoked');
        $table->dateTime('user_approved_at')->nullable();
        $table->dateTime('last_polled_at')->nullable();
        $table->dateTime('expires_at')->nullable();
    });

    DB::table('oauth_clients')->insert([
        'id' => PRR_CLIENT_ID,
        'owner_type' => null,
        'owner_id' => null,
        'name' => 'Password reset test client',
        'secret' => null,
        // Matches auth.providers.users, so HasApiTokens::tokens() keeps the
        // token inside this account's provider scope.
        'provider' => 'users',
        'redirect_uris' => '[]',
        'grant_types' => '["password"]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. The whole credential surface goes
// ──────────────────────────────────────────────────────────────────────────────

it('revokes every credential of the account when the password is reset', function (): void {
    $user = prrUser();
    $tokenId = prrCredentials($user);

    expect(prrCredentialsLive($user, $tokenId))->toBeTrue();

    event(new PasswordReset($user));

    // The finding, reproduced: before the listener, the stolen access token and
    // the refresh token behind it both survived the reset, and so did every
    // open browser session.
    expect(prrCredentialsRevoked($user, $tokenId))->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Status-independent — the regression that a "reuse execute()" fix causes
// ──────────────────────────────────────────────────────────────────────────────

it('revokes on a reset even when the account stays active and status enforcement is off', function (): void {
    // Both gates that make RevokeUserAccessAction::execute() a no-op: the kill
    // switch is off AND the status does not transition into a denied value.
    config(['starter-kit.security.enforce_active_status' => false]);

    $user = prrUser();
    $tokenId = prrCredentials($user);

    event(new PasswordReset($user));

    expect(prrCredentialsRevoked($user, $tokenId))->toBeTrue()
        // The reset changes nothing about the account's standing — it must stay
        // usable the moment the owner logs back in.
        ->and($user->refresh()->status)->toBe('active');
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Blast radius: one account, and never the reset request's own session
// ──────────────────────────────────────────────────────────────────────────────

it('leaves other accounts and the guest session of the reset request alone', function (): void {
    $user = prrUser();
    $tokenId = prrCredentials($user);

    $bystander = prrUser('grace-reset@example.test');
    $bystanderToken = prrCredentials($bystander);

    // Fortify resets WITHOUT logging anyone in, so the session behind the reset
    // request belongs to a guest. If the purge matched it, the redirect to
    // login would lose its flashed status message.
    $guestSession = prrSession(null);

    event(new PasswordReset($user));

    expect(prrCredentialsRevoked($user, $tokenId))->toBeTrue()
        ->and(prrCredentialsLive($bystander, $bystanderToken))->toBeTrue()
        ->and(DB::table('sessions')->where('id', $guestSession)->exists())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Risk notes — the two documented silent skips
// ──────────────────────────────────────────────────────────────────────────────

it('still revokes the tokens but touches no session row on a non-database session driver', function (): void {
    // file/redis/cookie stores have no user→session index, so there is nothing
    // to delete without scanning the whole store. The tokens are unaffected by
    // that limitation and must still go.
    config(['session.driver' => 'redis']);

    $user = prrUser();
    $tokenId = prrCredentials($user);

    event(new PasswordReset($user));

    expect(DB::table('oauth_access_tokens')->where('id', $tokenId)->where('revoked', false)->exists())->toBeFalse()
        ->and(DB::table('oauth_refresh_tokens')->where('access_token_id', $tokenId)->where('revoked', false)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->getKey())->exists())->toBeTrue();
});

it('does not fail a reset whose event carries something that is not an authenticatable', function (): void {
    $user = prrUser();
    $tokenId = prrCredentials($user);

    // A consumer firing the framework event with an id rather than the model.
    // Nothing is revoked — there is no account to resolve — but the reset that
    // already committed must not become a 500 the user retries with a token the
    // broker has consumed.
    (new RevokeCredentialsOnPasswordReset(app(RevokeUserAccessAction::class)))
        ->handle(new PasswordReset($user->getKey()));

    expect(prrCredentialsLive($user, $tokenId))->toBeTrue();
});
