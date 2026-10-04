<?php

namespace Lvntr\StarterKit\Domain\Session\Actions;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: End every other browser session of the authenticated user, on every
 * session driver. Validates the user's password before proceeding.
 *
 * Three layers, each closing what the previous one cannot:
 *
 *   1. REHASH → AuthenticateSession. The password is re-hashed (same plain
 *      text, new hash) through the session guard's logoutOtherDevices(). Every
 *      session is stamped with the hash it was authenticated against, so the
 *      AuthenticateSession middleware the kit attaches to `web` logs every
 *      other session out on its next request — file, redis, memcached and
 *      cookie stores included, none of which can be searched by user. The
 *      current session is restamped by that same middleware after the
 *      response, so this device stays signed in.
 *   2. REMEMBER TOKEN CYCLE. A fresh remember token is persisted in the same
 *      write, so every other device's remember-me cookie stops resolving a
 *      user even before its embedded hash is compared. logoutOtherDevices()
 *      re-queues the current device's recaller cookie with the new token and
 *      hash, so this device keeps its remember-me cookie.
 *   3. DATABASE ROW DELETE. On the `database` driver the user's other session
 *      rows are deleted immediately, so they leave the Sessions list now
 *      instead of on their next request.
 *
 * WHY Model::withoutEvents — the password did not change, it was only
 * re-hashed. With model events on, the User `saving` hook would move
 * `password_changed_at` (resetting the password-expiry clock) and the
 * activity log would record a password change that never happened. The
 * OtherDeviceLogout event still fires: it goes through the guard's own
 * dispatcher, not the model one.
 */
class PurgeOtherSessionsAction extends BaseAction
{
    /**
     * Execute the action.
     *
     * @throws ValidationException when the password is wrong
     * @throws LogicException when the default guard is not a session guard
     *                        authenticated as `$user` — a purge that cannot
     *                        run must never report success
     */
    public function execute(Authenticatable $user, string $password, string $currentSessionId): void
    {
        if (! Hash::check($password, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password' => [__('The provided password is incorrect.')],
            ]);
        }

        // Larastan types this from the package's own auth config; the
        // consumer's config decides the default guard at runtime.
        /** @var Guard $guard */
        $guard = Auth::guard();

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Purging other sessions requires the default guard to be a session guard.');
        }

        $guardUser = $guard->user();

        if ($guardUser === null || $guardUser->getAuthIdentifier() !== $user->getAuthIdentifier()) {
            throw new LogicException('Purging other sessions requires the session guard to be authenticated as the same user.');
        }

        // One save() inside the guard's rehash persists the new hash AND the
        // new remember token — set on the guard's OWN user instance, the one
        // that rehash writes and AuthenticateSession restamps from.
        Model::withoutEvents(function () use ($guard, $guardUser, $password): void {
            $guardUser->setRememberToken(Str::random(60));
            $guard->logoutOtherDevices($password);
        });

        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->getAuthIdentifier())
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
