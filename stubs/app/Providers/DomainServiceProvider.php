<?php

namespace App\Providers;

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
        // ── User / Role / Logs Events ────────────────────────────────────────
        // These domains were moved vendor-first: their events + Log* listeners now
        // live in Lvntr\StarterKit\Domain\{User,Role,Logs}\* and the vendor actions
        // (Create/Update/Delete*) dispatch the VENDOR event. The kit's
        // StarterKitServiceProvider::registerEventListeners() binds each event to its
        // listener with the vendor FQCN on both sides, so the registration key matches
        // the dispatched class. An App-keyed Event::listen here would never match that
        // vendor dispatch (class_alias does not rewrite a `::class` literal), so the
        // bindings are omitted.
        //
        // Add your OWN application event→listener bindings here. Re-add a binding for
        // a kit domain only if you reintroduce an App\ copy of BOTH the event and its
        // dispatching action.
    }
}
