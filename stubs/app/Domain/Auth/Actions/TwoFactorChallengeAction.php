<?php

namespace App\Domain\Auth\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Complete the API two-factor challenge issued by LoginUserAction.
 *
 * Validates either a TOTP `code` or a one-shot `recovery_code`, consumes the
 * one-time challenge record, and issues an access token on success.
 *
 * Single-use enforcement comes in two layers.
 *
 * CONCURRENCY — the challenge is claimed with Cache::add() on a companion key
 * as the FIRST statement. add() is add-if-absent and every driver this kit
 * supports implements it inside the STORE, as one indivisible operation:
 * Redis runs a single Lua `exists`/`setex` script, Memcached uses the
 * protocol's native `add`, the database store issues an `insertOrIgnore`
 * against the cache table's primary key, and the file store writes behind a
 * non-blocking `LOCK_EX` flock whose loser is refused rather than served. The
 * `array` store declares no add() of its own, so Illuminate\Cache\Repository
 * falls back to get-then-put there — that store is per-process memory, where
 * there is no second request to race with. Exactly one concurrent redemption
 * of a given challenge therefore gets past the first statement.
 *
 * SEQUENCE — the winner then pulls the payload, so every later exit path
 * (user gone, inactive, bad TOTP, bad recovery code, no input at all) leaves
 * the challenge spent and unusable for the rest of its 5-minute TTL.
 *
 * Recovery codes carry a third, independent guarantee: their read-match-replace
 * runs inside a transaction holding a row lock on the user.
 *
 * @return array{user: User, token: string}|null
 */
class TwoFactorChallengeAction extends BaseAction
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

    /**
     * @return array{user: User, token: string}|null
     */
    public function execute(string $challenge, ?string $code, ?string $recoveryCode): ?array
    {
        // Claim the challenge before anything else, with the one cache
        // primitive that is atomic inside the store: Cache::add() creates the
        // companion claim key only if no other request already did, and it
        // returns WHICH caller created it. Exactly one concurrent redemption
        // can win; every other one is refused here, indistinguishable from a
        // replay.
        //
        // Why not Cache::pull(), which this replaces: it reads like a claim but
        // Illuminate\Cache\Repository::pull() is
        // `tap($this->get($key), fn () => $this->forget($key))` — a separate
        // read and delete, on EVERY driver. Two requests could both read the
        // user id before either delete landed, and both could mint an access
        // token. `throttle:5,1` on the route rate-limits that race; it does not
        // serialize it.
        //
        // Why not Cache::lock(): on the `database` cache driver a lock needs
        // the separate `cache_locks` table, so an install that never created it
        // would get a hard failure on the 2FA endpoint instead of a login.
        // add() needs nothing beyond the cache store itself.
        //
        // The claim TTL is the challenge TTL, read from the same constant. The
        // claim is always written no earlier than the payload it guards, so it
        // always expires no earlier either — there is no window in which the
        // payload is still alive with the claim already gone.
        $claimed = Cache::add(
            LoginUserAction::challengeClaimKey($challenge),
            true,
            LoginUserAction::TWO_FACTOR_CHALLENGE_TTL,
        );

        if (! $claimed) {
            return null;
        }

        // Sole winner. Read the payload and delete it in the same breath: no
        // later exit path has to remember to forget it, and the claim key above
        // already blocks a retry even if this pull leaves anything behind.
        $cacheKey = LoginUserAction::challengeKey($challenge);
        $userId = Cache::pull($cacheKey);

        if ($userId === null) {
            return null;
        }

        $user = User::find($userId);

        if (! $user || $user->two_factor_secret === null || $user->two_factor_confirmed_at === null) {
            return null;
        }

        if ($user->status !== 'active') {
            return null;
        }

        // Challenge is single-use — any failed attempt (wrong TOTP, wrong
        // recovery code, or missing input) has already consumed it above: the
        // payload is deleted AND the claim key stands for the rest of the TTL.
        // Neither the remaining 5 minutes nor a race can be used to brute-force
        // the 6-digit code.
        if ($code !== null && $code !== '') {
            $valid = $this->provider->verify(
                Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
                $code,
            );

            if (! $valid) {
                return null;
            }
        } elseif ($recoveryCode !== null && $recoveryCode !== '') {
            if (! $this->consumeRecoveryCode($user, $recoveryCode)) {
                return null;
            }
        } else {
            return null;
        }

        $token = $user->createToken('auth-token')->accessToken;

        return [
            'user' => $user->refresh(),
            'token' => $token,
        ];
    }

    /**
     * Spend a recovery code, exactly once, even under concurrent requests.
     *
     * Recovery codes live as one encrypted JSON blob on the user row, so
     * "match then replace" is a read-modify-write. Two requests carrying the
     * same code could both read the old blob and both find a match before
     * either write lands — two access tokens from one code. Locking the user
     * row inside a transaction serializes them: the second request blocks
     * until the first commits, then re-reads the rewritten blob and no longer
     * finds the code.
     *
     * The re-read inside the lock is the point — matching against the
     * caller's already-loaded $user would defeat it. On SQLite the row lock
     * compiles to a no-op (the whole transaction is serialized instead), which
     * is why a genuine race cannot be reproduced in the package test suite.
     */
    private function consumeRecoveryCode(User $user, string $recoveryCode): bool
    {
        return DB::transaction(function () use ($user, $recoveryCode): bool {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->two_factor_recovery_codes === null) {
                return false;
            }

            $match = collect($locked->recoveryCodes())->first(
                fn (string $stored) => hash_equals($stored, $recoveryCode),
            );

            if ($match === null) {
                return false;
            }

            $locked->replaceRecoveryCode($match);

            return true;
        });
    }
}
