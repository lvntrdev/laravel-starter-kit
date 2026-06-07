<?php

namespace App\Providers;

use App\Domain\Role\Events\RoleCreated;
use App\Domain\Role\Events\RoleDeleted;
use App\Domain\Role\Events\RoleUpdated;
use App\Domain\Role\Listeners\LogRoleCreated;
use App\Domain\Role\Listeners\LogRoleDeleted;
use App\Domain\Role\Listeners\LogRoleUpdated;
use App\Domain\User\Events\UserCreated;
use App\Domain\User\Events\UserDeleted;
use App\Domain\User\Events\UserUpdated;
use App\Domain\User\Listeners\LogUserCreated;
use App\Domain\User\Listeners\LogUserDeleted;
use App\Domain\User\Listeners\LogUserUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers domain events and listeners.
 *
 * Event → Listener mappings keep side effects decoupled from actions.
 */
class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        //
    ];

    /**
     * Bootstrap domain events and listeners.
     */
    public function boot(): void
    {
        // ── User Events ──────────────────────────────────────────────────
        Event::listen(UserCreated::class, LogUserCreated::class);
        Event::listen(UserUpdated::class, LogUserUpdated::class);
        Event::listen(UserDeleted::class, LogUserDeleted::class);

        // ── Role Events ──────────────────────────────────────────────────
        Event::listen(RoleCreated::class, LogRoleCreated::class);
        Event::listen(RoleUpdated::class, LogRoleUpdated::class);
        Event::listen(RoleDeleted::class, LogRoleDeleted::class);

        // ── Logs Events ──────────────────────────────────────────────────
        // The Logs domain was moved vendor-first: its event + listener now live
        // in Lvntr\StarterKit\Domain\Logs\* and the vendor DeleteLogFilesAction
        // dispatches the VENDOR event. The kit's StarterKitServiceProvider binds
        // LogFilesDeleted → LogActivityForLogFilesDeleted with the vendor FQCN on
        // both sides, so the registration key matches the dispatched class. An
        // App-keyed Event::listen here would never match that vendor dispatch
        // (class_alias does not rewrite a `::class` literal), so it is omitted.
        // Re-add a binding here only if you reintroduce an App\ copy of both the
        // event and its dispatching action.
    }
}
