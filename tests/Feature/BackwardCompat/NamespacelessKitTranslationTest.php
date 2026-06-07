<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — Namespace-less Kit Translation Resolution
|--------------------------------------------------------------------------
|
| v15.x+ (Faz 5): the kit's `sk-*` UI translations moved from the install
| bulk-copy (stubs/lang → app/lang) to vendor-resident files
| (resources/lang/{en,tr}/sk-*.php). 21 vendor src/ files call these keys
| WITHOUT a namespace, e.g. __('sk-bulk.result'). They must keep resolving
| even when the consumer app has NO app/lang copy (fresh install after the
| bulk-copy was cut).
|
| StarterKitServiceProvider::registerNamespacelessKitTranslations() makes the
| vendor resources/lang dir resolvable namespace-less by inserting it into the
| translation loader's path list BEFORE the app/lang path — so:
|   - vendor sk-* keys resolve with no namespace, and
|   - a consumer's own app/lang/{locale}/sk-*.php override still WINS (the
|     override invariant: app copies are never silently shadowed).
|
*/

use Illuminate\Support\Facades\File;

it('resolves vendor sk-* keys without a namespace prefix', function (): void {
    app()->setLocale('en');

    // A bare group key, exactly as the vendor src/ callers use it.
    expect(__('sk-bulk.no_authorized_items'))
        ->toBe('You are not authorized to perform this action on any of the selected items.');

    // Parameter replacement still works (BulkActionDispatcher uses this shape).
    expect(__('sk-bulk.result', ['processed' => 3, 'skipped' => 1, 'failed' => 0]))
        ->toBe('3 item(s) processed, 1 skipped, 0 failed.');
});

it('resolves the tr locale from the vendor copy', function (): void {
    app()->setLocale('tr');

    // tr translation differs from en, proving the tr vendor file is loaded.
    expect(__('sk-bulk.no_authorized_items'))
        ->not->toBe('sk-bulk.no_authorized_items')
        ->not->toBe('You are not authorized to perform this action on any of the selected items.');
});

it('lets a consumer app/lang override win over the vendor copy', function (): void {
    $langPath = app()->langPath('en');
    $overrideFile = $langPath.'/sk-bulk.php';
    $created = ! File::isDirectory($langPath);

    File::ensureDirectoryExists($langPath);
    File::put($overrideFile, "<?php\n\nreturn ['no_authorized_items' => 'OVERRIDDEN'];\n");

    try {
        // The translator caches loaded groups per request; rebind a fresh
        // translator so the new override file is picked up in this assertion.
        app()->forgetInstance('translator');
        app()->forgetInstance('translation.loader');
        app()->setLocale('en');

        // App override wins for the key it defines...
        expect(__('sk-bulk.no_authorized_items'))->toBe('OVERRIDDEN');

        // ...while keys the override does NOT define still fall back to vendor.
        expect(__('sk-bulk.result', ['processed' => 1, 'skipped' => 0, 'failed' => 0]))
            ->toBe('1 item(s) processed, 0 skipped, 0 failed.');
    } finally {
        File::delete($overrideFile);
        if ($created) {
            File::deleteDirectory($langPath);
        }
    }
});
