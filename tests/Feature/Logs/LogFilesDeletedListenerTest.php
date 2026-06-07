<?php

/*
|--------------------------------------------------------------------------
| Logs audit listener — fresh-install (vendor) regression
|--------------------------------------------------------------------------
|
| Faz 3 moved the Logs domain vendor-first. The audit listener
| (LogActivityForLogFilesDeleted) was still registered by the consumer's
| stub DomainServiceProvider under the `App\Domain\Logs\Events\LogFilesDeleted`
| literal — a plain lexical `::class` string that class_alias never rewrites.
| The vendor DeleteLogFilesAction dispatches the VENDOR LogFilesDeleted, whose
| get_class() is `Lvntr\StarterKit\Domain\Logs\Events\LogFilesDeleted`. The
| dispatcher matches listeners by string key, so the two strings never met and
| the audit log was silently lost on every fresh install.
|
| Fix: StarterKitServiceProvider::registerEventListeners() now binds the
| listener with the vendor FQCN on BOTH sides; the stub no longer registers it.
|
| These tests prove the REAL dispatch→listen match (not Event::fake — a fake
| would mask the exact key-mismatch bug). The listener implements ShouldQueue,
| so the queue is pinned to `sync` to run it inline and assert its side effect
| (the activity('system') row).
|
| The activity_log table is built inline here (mirroring the project's
| Schema-builder test convention) because DatabaseTestCase only provisions the
| FileManager/settings tables.
|
*/

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\Logs\Events\LogFilesDeleted;
use Lvntr\StarterKit\Domain\Logs\Listeners\LogActivityForLogFilesDeleted;
use Spatie\Activitylog\Models\Activity;

const APP_LOG_FILES_DELETED = 'App\\Domain\\Logs\\Events\\LogFilesDeleted';

beforeEach(function (): void {
    // ShouldQueue listener → run inline so the audit side effect is observable
    // synchronously. Testbench defaults queue.default to `database`, whose
    // `jobs` table does not exist here; `sync` executes the listener in-process.
    config(['queue.default' => 'sync']);

    // activity('system') target table — Spatie's Activity model writes here.
    Schema::create('activity_log', function (Blueprint $table): void {
        $table->id();
        $table->string('log_name')->nullable()->index();
        $table->text('description');
        $table->nullableMorphs('subject', 'subject');
        $table->string('event')->nullable();
        $table->nullableMorphs('causer', 'causer');
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });
});

it('registers the audit listener under the VENDOR event FQCN', function (): void {
    // The exact assertion for the bug: the registration key must equal the
    // class the vendor action dispatches. Under the old App-keyed registration
    // this was false → the listener never fired on a fresh install.
    expect(Event::hasListeners(LogFilesDeleted::class))->toBeTrue(
        'Vendor LogFilesDeleted must have a registered listener (the audit hook).',
    );

    $listeners = array_map(
        static fn ($closure): string => (new ReflectionFunction($closure))->getStaticVariables()['listener'] ?? '',
        Event::getListeners(LogFilesDeleted::class),
    );

    expect($listeners)->toContain(LogActivityForLogFilesDeleted::class);
});

it('does NOT keep the listener under the App event key — no double-fire path', function (): void {
    // The stub DomainServiceProvider no longer registers the App-keyed binding.
    // If it lingered, a reconciled consumer (App import aliased to vendor) could
    // not double-fire anyway (only the vendor key matches the vendor dispatch),
    // but asserting its absence pins the single, unambiguous registration path.
    expect(Event::hasListeners(APP_LOG_FILES_DELETED))->toBeFalse(
        'App-keyed LogFilesDeleted listener must be gone after the vendor move.',
    );
});

it('writes exactly one system audit entry when the vendor event is really dispatched', function (): void {
    // Real dispatch (fresh-install path): vendor object → vendor key match →
    // listener runs inline → one activity('system') row.
    LogFilesDeleted::dispatch(['laravel-2026-06-07.log', 'laravel-2026-06-06.log'], null);

    $entries = Activity::query()->where('log_name', 'system')->get();

    expect($entries)->toHaveCount(1);

    $entry = $entries->first();

    expect($entry->description)->toBe('Deleted 2 log file(s)')
        ->and($entry->getProperty('filenames'))->toBe([
            'laravel-2026-06-07.log',
            'laravel-2026-06-06.log',
        ])
        ->and($entry->causer_id)->toBeNull();
});

it('fires the listener exactly once per dispatch (no duplicate audit rows)', function (): void {
    // Guard against any accidental second registration causing duplicate audit
    // records. A single dispatch must yield a single row.
    LogFilesDeleted::dispatch(['laravel-2026-06-05.log'], null);

    expect(Activity::query()->where('log_name', 'system')->count())->toBe(1);
});

it('writes nothing when the deleted-file list is empty', function (): void {
    // The listener early-returns on an empty payload; the action also guards the
    // dispatch, but the listener stays defensive. No row should be written.
    LogFilesDeleted::dispatch([], null);

    expect(Activity::query()->where('log_name', 'system')->count())->toBe(0);
});
