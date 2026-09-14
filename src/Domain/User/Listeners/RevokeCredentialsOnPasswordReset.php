<?php

namespace Lvntr\StarterKit\Domain\User\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Domain\User\Actions\RevokeUserAccessAction;
use Throwable;

/**
 * Kill every credential the old password ever bought, the moment it is reset.
 *
 * ── WHY THE FRAMEWORK EVENT AND NOT THE CONTROLLER ──────────────────────────
 *
 * `Illuminate\Auth\Events\PasswordReset` is the one place every reset path
 * converges: Fortify's web reset fires it from CompletePasswordReset, and any
 * API or console reset a consumer adds fires it too, because the broker
 * callback is where Laravel documents it. Hooking the Fortify controller
 * instead would have covered exactly one of those and silently missed the
 * rest. The event ships with `illuminate/auth`, so this listener also stands
 * on an install that never enabled Fortify or Passport.
 *
 * ── DELIBERATELY NOT QUEUED ─────────────────────────────────────────────────
 *
 * This class does NOT implement ShouldQueue, unlike the audit listeners next
 * to it. An audit row that lands a second late is still a correct audit row; a
 * revocation that lands a second late is a window in which a stolen token
 * still works — and on a stalled or misconfigured queue that window is
 * unbounded. Revocation runs inside the reset request or it is not a
 * revocation. The work is a handful of indexed UPDATEs plus one DELETE on a
 * request that already sends mail, so the cost is not the deciding factor
 * here; the guarantee is.
 *
 * ── FAILURE POSTURE ─────────────────────────────────────────────────────────
 *
 * A reset that 500s is a reset the user repeats with a token the broker has
 * already consumed, which locks them out of their own account. So nothing
 * escapes this method: the action guards every step internally and never
 * throws, and the try/catch here covers the remaining shapes (a container that
 * cannot build the action, a user object the event carried in a form the
 * action cannot read). The password change itself has already committed either
 * way.
 *
 * @see RevokeUserAccessAction::executeForCredentialRotation()
 */
class RevokeCredentialsOnPasswordReset
{
    public function __construct(private readonly RevokeUserAccessAction $revoker) {}

    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        $user = $this->userOf($event);

        // The guard is real, not defensive decoration — see userOf().
        if (! $user instanceof Authenticatable) {
            return;
        }

        try {
            $this->revoker->executeForCredentialRotation($user);
        } catch (Throwable $e) {
            Log::warning('starter-kit: credentials could not be revoked after a password reset.', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The account the event carries, read as the UNTYPED value it really is.
     *
     * `Illuminate\Auth\Events\PasswordReset::$user` is declared `public $user`
     * with no native type; the `Authenticatable` in its docblock documents
     * Laravel's OWN callers and constrains nobody else. `event(new
     * PasswordReset($id))` is legal PHP, consumers write it, and taking the
     * docblock at its word here would turn that into a TypeError on a password
     * reset that has already committed — locking the user out with a broker
     * token they have already spent. Reading through a `mixed` return states
     * the real contract so the instanceof above is a check, not a formality.
     */
    private function userOf(PasswordReset $event): mixed
    {
        return $event->user;
    }
}
